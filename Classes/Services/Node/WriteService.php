<?php
namespace UpAssist\NodeApi\Services\Node;

use Neos\Flow\Annotations as Flow;
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
use Neos\ContentRepository\Utility;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
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
     * @var LoggerInterface
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
        if ($type === 'Neos\Media\Domain\Model\Asset' && $this->isUpload($propertyValue)) {
            $resource = $this->writeFile($propertyValue);
            if ($resource instanceof PersistentResource) {
                $fileToAdd = $this->assetRepository->findOneByResourceSha1($resource->getSha1());
                if (!$fileToAdd instanceof Asset) {
                    $fileToAdd = new Asset($resource);
                    $this->assetRepository->add($fileToAdd);
                    $this->persistenceManager->persistAll();
                }
                $propertyValue = $fileToAdd;
            } else {
                // Never store a rejected or failed upload as raw property value
                $propertyValue = null;
            }

        }

        // Check for image uploads
        if ($type === 'Neos\Media\Domain\Model\ImageInterface' && $this->isUpload($propertyValue)) {
            $resource = $this->writeFile($propertyValue);
            if ($resource instanceof PersistentResource) {
                $fileToAdd = $this->imageRepository->findOneByResourceSha1($resource->getSha1());
                if (!$fileToAdd instanceof Image) {
                    $fileToAdd = new Image($resource);
                    $this->imageRepository->add($fileToAdd);
                    $this->persistenceManager->persistAll();
                }
                $propertyValue = $fileToAdd;
            } else {
                // Never store a rejected or failed upload as raw property value
                $propertyValue = null;
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
     * @param mixed $value
     * @return bool
     */
    private function isUpload($value): bool
    {
        return $value instanceof UploadedFileInterface || is_array($value);
    }

    /**
     * Import an uploaded file as persistent resource.
     *
     * Accepts PSR-7 uploaded files (how Flow passes uploads to actions) and
     * $_FILES-style arrays, but only when "tmp_name" is a real HTTP upload.
     * Anything else is rejected, so request data can never point at an
     * arbitrary file on the server.
     *
     * @param UploadedFileInterface|array $file
     * @return PersistentResource|null
     */
    private function writeFile($file): ?PersistentResource
    {
        if ($file instanceof UploadedFileInterface) {
            if ($file->getError() !== UPLOAD_ERR_OK) {
                return null;
            }
            $stream = $file->getStream();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            $fileContent = $stream->getContents();
            $fileName = (string)$file->getClientFilename();
        } elseif (is_array($file)
            && isset($file['tmp_name'], $file['name'])
            && is_string($file['tmp_name'])
            && is_uploaded_file($file['tmp_name'])
        ) {
            $fileContent = file_get_contents($file['tmp_name']);
            $fileName = (string)$file['name'];
        } else {
            $this->systemLogger->warning('UpAssist.NodeApi: rejected file value that is not an HTTP upload');
            return null;
        }

        if ($fileContent === false || $fileContent === '') {
            return null;
        }

        $resource = $this->resourceManager->getResourceBySha1(sha1($fileContent));
        if ($resource !== null) {
            return $resource;
        }

        try {
            return $this->resourceManager->importResourceFromContent($fileContent, basename($fileName));
        } catch (\Exception $exception) {
            $this->systemLogger->error('UpAssist.NodeApi: could not import uploaded file: ' . $exception->getMessage());
        }

        return null;
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
