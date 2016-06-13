<?php
namespace UpAssist\NodeApi\Services\Node;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Log\SystemLoggerInterface;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Flow\Resource\Resource;
use TYPO3\Flow\Resource\ResourceManager;
use TYPO3\Media\Domain\Model\Asset;
use TYPO3\Media\Domain\Model\Image;
use TYPO3\Media\Domain\Repository\AssetRepository;
use TYPO3\Media\Domain\Repository\ImageRepository;
use TYPO3\Neos\TypoScript\Cache\ContentCacheFlusher;
use TYPO3\TYPO3CR\Domain\Model\Node;
use TYPO3\TYPO3CR\Domain\Model\NodeTemplate;
use TYPO3\TYPO3CR\Domain\Model\Workspace;
use TYPO3\TYPO3CR\Domain\Service\NodeTypeManager;
use TYPO3\TYPO3CR\Domain\Service\PublishingService;
use TYPO3\TYPO3CR\Utility;
use UpAssist\NodeApi\Services\ContentContextService;

/**
 * Class WriteService
 * @package UpAssist\NodeApi\Services\Node
 * @Flow\Scope("singleton")
 */
class WriteService
{
    /**
     * @Flow\Inject
     * @var ContentContextService
     */
    protected $contentContextService;

    /**
     * @Flow\Inject
     * @var ReadService
     */
    protected $nodeReadService;

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @Flow\Inject
     * @var SystemLoggerInterface
     */
    protected $systemLogger;

    /**
     * @Flow\Inject
     * @var AssetRepository
     */
    protected $assetRepository;

    /**
     * @Flow\Inject
     * @var ImageRepository
     */
    protected $imageRepository;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var ContentCacheFlusher
     */
    protected $contentCacheFlusher;

    /**
     * @param string $referenceNodeIdentifier
     * @param string $nodeType
     * @param array $nodeData
     * @return Node|null
     * @throws \TYPO3\TYPO3CR\Exception\NodeException
     */
    public function createNode($referenceNodeIdentifier, $nodeType, $nodeData = [])
    {
        /** @var Node $referenceNode */
        $referenceNode = $this->nodeReadService->getNodeByIdentifier($referenceNodeIdentifier);

        $nodeTemplate = new NodeTemplate();
        $nodeTemplate->setNodeType($this->nodeTypeManager->getNodeType($nodeType));

        if (isset($nodeData['hiddenAfterDateTime'])) {
            $nodeTemplate->setHiddenAfterDateTime(new \DateTime($nodeData['hiddenAfterDateTime']));
        }

        if (isset($nodeData['properties'])) {
            foreach ($nodeData['properties'] as $propertyName => $propertyValue) {

                // Match the uripathsegment
                if ($propertyName === 'title' && $nodeTemplate->getNodeType()->isOfType('TYPO3.Neos:Document')) {
                    $newUriPathSegment = strtolower(Utility::renderValidNodeName($propertyValue));
                    $nodeTemplate->setProperty('uriPathSegment', $newUriPathSegment);
                }

                // Set the properties
                $nodeTemplate->setProperty($propertyName, $this->propertyValueMapper($propertyValue));
            }
        }

        if ($referenceNode instanceof Node) {

            $node = $referenceNode->createNodeFromTemplate($nodeTemplate);

            /**
             * Enforce publication update when in live workspace
             *
             * @var Workspace $workspace
             */
            $workspace = $node->getWorkspace();
            if ($workspace->getName() === 'live') {
                $node->getNodeData()->setLastPublicationDateTime(new \DateTime());
            }

            return $node;
        }

        return null;
    }

    /**
     * @param Node $node
     */
    public function deleteNode(Node $node) {
        $node->setRemoved(true);
        $this->persistenceManager->persistAll();
    }

    /**
     * @param Node $parentNode
     * @param string $nodePath
     * @param string $contentNodeType
     * @param array $contentNodeData
     * @return \TYPO3\TYPO3CR\Domain\Model\NodeInterface
     */
    public function addContent(Node $parentNode, $nodePath, $contentNodeType, $contentNodeData = [])
    {
        $nodeTemplate = new NodeTemplate();
        $nodeTemplate->setNodeType($this->nodeTypeManager->getNodeType($contentNodeType));

        if (isset($contentNodeData['properties'])) {
            foreach ($contentNodeData['properties'] as $propertyName => $propertyValue) {
                $nodeTemplate->setProperty($propertyName, $this->propertyValueMapper($propertyValue));
            }
        }

        return $parentNode->getNode($nodePath)->createNodeFromTemplate($nodeTemplate);
    }


    /**
     * Use this to update some meta properties of the Node.
     *
     * @param Node $node
     * @param array $nodeData
     * @return Node
     */
    public function updateNodeData(Node $node, $nodeData = []) {
        if (isset($nodeData['hiddenAfterDateTime'])) {
            $node->setHiddenAfterDateTime(new \DateTime($nodeData['hiddenAfterDateTime']));
        }

        $this->persistenceManager->persistAll();
        $this->contentCacheFlusher->registerNodeChange($node);

        return $node;
    }

    /**
     * @param Node $node
     * @param array $properties
     * @return Node
     */
    public function updateNodeProperties(Node $node, $properties = []) {

        foreach ($properties as $property => $value) {
            if ($property === 'title' && $node->getNodeType()->isOfType('TYPO3.Neos:Document')) {
                $newUriPathSegment = strtolower(Utility::renderValidNodeName($value));
                $node->setProperty('uriPathSegment', $newUriPathSegment);
            }

            $node->getNodeData()->setProperty($property, $this->propertyValueMapper($value));
        }

        if ($node->getNodeType()->isOfType('TYPO3.Neos:Document')) {
            $node->getNodeData()->setLastPublicationDateTime(new \DateTime());
        }

        $this->persistenceManager->persistAll();
        $this->contentCacheFlusher->registerNodeChange($node);

        return $node;
    }

    /**
     * @param mixed $propertyValue
     * @return string|NULL|Asset|\TYPO3\Media\Domain\Model\AssetInterface|Image
     * @throws \TYPO3\Flow\Persistence\Exception\IllegalObjectTypeException
     */
    private function propertyValueMapper($propertyValue) {

        // Check for file uploads
        if(is_array($propertyValue) && count($propertyValue) == 5) {
            /** @var \TYPO3\Flow\Resource\Resource $resource */
            $resource = $this->writeFile($propertyValue);
            if ($resource instanceof Resource) {

                switch ($resource->getMediaType()) {
                    case (preg_match("/^image\/.*/", $resource->getMediaType()) ? true : false):
                        $fileToAdd = $this->imageRepository->findOneByResourceSha1($resource->getSha1());
                        if (!$fileToAdd instanceof Image) {
                            $fileToAdd = new Image($resource);
                            $this->imageRepository->add($fileToAdd);
                            $this->persistenceManager->persistAll();
                        }
                        break;
                    default:
                        $fileToAdd = $this->assetRepository->findOneByResourceSha1($resource->getSha1());
                        if (!$fileToAdd instanceof Asset) {
                            $fileToAdd = new Asset($resource);
                            $this->assetRepository->add($fileToAdd);
                            $this->persistenceManager->persistAll();
                        }
                        break;
                }

                $propertyValue = $fileToAdd;
            }

        }

        return $propertyValue;
    }

    /**
     * @param array $file
     * @return Resource
     */
    private function writeFile(array $file)
    {
        $fileContent = file_get_contents($file['tmp_name']);
        $hash = sha1($fileContent);

        $resource = $this->resourceManager->getResourceBySha1($hash);

        if ($resource !== NULL) {
            return $resource;
        }

        try {
            $resource = $this->resourceManager->importResourceFromContent($fileContent, $file['name']);
        } catch (\Exception $exception) {
            $this->systemLogger->log('Exception during url to file conversion: ' . $exception->getMessage());
        }

        return $resource;
    }



}
