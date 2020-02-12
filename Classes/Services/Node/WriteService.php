<?php
namespace UpAssist\NodeApi\Services\Node;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cache\CacheManager;
use Neos\Flow\Log\SystemLoggerInterface;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Media\Domain\Model\Asset;
use Neos\Media\Domain\Model\Image;
use Neos\Media\Domain\Repository\AssetRepository;
use Neos\Media\Domain\Repository\ImageRepository;
use Neos\Neos\Fusion\Cache\ContentCacheFlusher;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeTemplate;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Domain\Service\PublishingService;
use Neos\ContentRepository\Migration\Configuration\ConfigurationInterface;
use Neos\ContentRepository\Utility;
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
     * @Flow\SkipCsrfProtection
     * @param string $referenceNodeIdentifier
     * @param string $nodeType
     * @param array $nodeData
     * @return Node|null
     * @throws \Neos\ContentRepository\Exception\NodeException
     */
    public function createNode($referenceNodeIdentifier, $nodeType, $nodeData = [])
    {
        $dimensions = isset($nodeData['dimensions']) ? $nodeData['dimensions'] : [];
        /** @var Node $referenceNode */
        $referenceNode = $this->nodeReadService->getNodeByIdentifier($referenceNodeIdentifier, $dimensions);

        /** @var NodeType $nodeTypeObject */
        $nodeTypeObject = $this->nodeTypeManager->getNodeType($nodeType);
        $nodeTemplate = new NodeTemplate();
        $nodeTemplate->setNodeType($nodeTypeObject);

        if (isset($nodeData['hiddenAfterDateTime']) && $nodeData['hiddenAfterDateTime'] > 0) {
            $nodeTemplate->setHiddenAfterDateTime(new \DateTime($nodeData['hiddenAfterDateTime']));
        }

        if (isset($nodeData['properties'])) {
            foreach ($nodeData['properties'] as $propertyName => $propertyValue) {

                // Match the uripathsegment
                if ($propertyName === 'title' && $nodeTemplate->getNodeType()->isOfType('Neos.Neos:Document')) {
                    $newUriPathSegment = strtolower(Utility::renderValidNodeName($propertyValue));
                    $nodeTemplate->setProperty('uriPathSegment', $newUriPathSegment);
                }

                // Set the properties
                $nodeTemplate->setProperty($propertyName, $this->propertyValueMapper($propertyValue, $this->getConfigurationTypeForPropertyByNode($propertyName, $nodeTemplate)));
            }
        }


        if ($referenceNode instanceof Node) {

            /** @var NodeInterface $node */
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
     * @Flow\SkipCsrfProtection
     * @param Node $node
     */
    public function deleteNode(Node $node)
    {
        $node->setRemoved(true);
        $this->persistenceManager->persistAll();
    }

    /**
     * @Flow\SkipCsrfProtection
     * @param Node $node
     */
    public function hideNode(Node $node)
    {
        $node->setHidden(true);
        $this->persistenceManager->persistAll();
    }

    /**
     * @Flow\SkipCsrfProtection
     * @param Node $parentNode
     * @param string $nodePath
     * @param string $contentNodeType
     * @param array $contentNodeData
     * @return \Neos\ContentRepository\Domain\Model\NodeInterface
     */
    public function addContent(Node $parentNode, $nodePath, $contentNodeType, $contentNodeData = [])
    {
        $nodeTemplate = new NodeTemplate();
        $nodeTemplate->setNodeType($this->nodeTypeManager->getNodeType($contentNodeType));

        if (isset($contentNodeData['properties'])) {
            foreach ($contentNodeData['properties'] as $propertyName => $propertyValue) {
                $nodeTemplate->setProperty($propertyName, $this->propertyValueMapper($propertyValue, $this->getConfigurationTypeForPropertyByNode($propertyName, $nodeTemplate)));
            }
        }

        return $parentNode->getNode($nodePath)->createNodeFromTemplate($nodeTemplate);
    }


    /**
     * Use this to update some meta properties of the Node.
     *
     * @Flow\SkipCsrfProtection
     * @param Node $node
     * @param array $nodeData
     * @return Node
     */
    public function updateNodeData(Node $node, $nodeData = [])
    {
        if (isset($nodeData['hiddenAfterDateTime'])) {
            if ($nodeData['hiddenAfterDateTime'] > 0) {
                $node->setHiddenAfterDateTime(new \DateTime($nodeData['hiddenAfterDateTime']));
            } else {
                $node->setHiddenAfterDateTime(null);
            }
        }

        $this->persistenceManager->persistAll();
        $this->contentCacheFlusher->registerNodeChange($node);

        return $node;
    }

    /**
     * @Flow\SkipCsrfProtection
     * @param Node $node
     * @param array $properties
     * @return Node
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     */
    public function updateNodeProperties(Node $node, $properties = [])
    {

        foreach ($properties as $property => $value) {
            if ($property === 'title' && $node->getNodeType()->isOfType('Neos.Neos:Document')) {
                $newUriPathSegment = strtolower(Utility::renderValidNodeName($value));
                $node->setProperty('uriPathSegment', $newUriPathSegment);
            }

            $node->getNodeData()->setProperty($property, $this->propertyValueMapper($value, $this->getConfigurationTypeForPropertyByNode($property, $node)));
        }

        if ($node->getNodeType()->isOfType('Neos.Neos:Document')) {
            $node->getNodeData()->setLastPublicationDateTime(new \DateTime());
        }

        $this->persistenceManager->persistAll();
        $this->contentCacheFlusher->registerNodeChange($node);

        return $node;
    }

    /**
     *
     * @param mixed $propertyValue
     * @param string $type
     * @return NULL|string|Asset|\Neos\Media\Domain\Model\AssetInterface|Image
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     */
    private function propertyValueMapper($propertyValue, $type)
    {

        // Check for file uploads
        if ($type === 'Neos\Media\Domain\Model\Asset') {
            /** @var \Neos\Flow\ResourceManagement\PersistentResource $resource */
            $resource = $this->writeFile($propertyValue);
            if ($resource instanceof Resource) {
                $fileToAdd = $this->assetRepository->findOneByResourceSha1($resource->getSha1());
                if (!$fileToAdd instanceof Asset) {
                    $fileToAdd = new Asset($resource);
                    $this->assetRepository->add($fileToAdd);
                    $this->persistenceManager->persistAll();
                }
                $propertyValue = $fileToAdd;
            }

        }

        // Check for image uploads
        if ($type === 'Neos\Media\Domain\Model\ImageInterface') {
            /** @var \Neos\Flow\ResourceManagement\PersistentResource $resource */
            $resource = $this->writeFile($propertyValue);
            if ($resource instanceof Resource) {
                $fileToAdd = $this->imageRepository->findOneByResourceSha1($resource->getSha1());
                if (!$fileToAdd instanceof Image) {
                    $fileToAdd = new Image($resource);
                    $this->imageRepository->add($fileToAdd);
                    $this->persistenceManager->persistAll();
                }
                $propertyValue = $fileToAdd;
            }

        }

        if ($type === 'DateTime' && $this->validateDate($propertyValue)) {
            $propertyValue = \DateTime::createFromFormat('Y-m-d\TH:i', $propertyValue);
        }

        if ($type === 'references') {
//            $propertyValue = json_encode($propertyValue);
        }

        return $propertyValue;
    }

    /**
     * @param string $property
     * @param NodeInterface|NodeTemplate $node
     * @return string
     */
    private function getConfigurationTypeForPropertyByNode($property, $node) {
        return $node->getNodeType()->getConfiguration('properties.' . $property . '.type');
    }

    /**
     * @Flow\SkipCsrfProtection
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


    /**
     * @param string $date
     * @return boolean
     */
    private function validateDate($date)
    {
        if (is_object($date) && (!$date instanceof \DateTime)) {
            return false;
        }

        if ($date instanceof \DateTime) {
            return true;
        }

        $d = \DateTime::createFromFormat('Y-m-d\TH:i', $date);
        return $d && $d->format('Y-m-d\TH:i') === $date;
    }

}
