<?php
namespace UpAssist\NodeApi\Services\Node;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Property\PropertyMapper;
use TYPO3\TYPO3CR\Domain\Service\Context;
use TYPO3\TYPO3CR\Domain\Factory\NodeFactory;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository;
use UpAssist\NodeApi\Services\ContentContextService;

/**
 * Class ReadService
 * @package UpAssist\NodeApi\Services\Node
 * @Flow\Scope("singleton")
 */
class ReadService
{

    /**
     * @Flow\Inject
     * @var NodeDataRepository
     */
    protected $nodeDataRepository;

    /**
     * @Flow\Inject
     * @var NodeFactory
     */
    protected $nodeFactory;

    /**
     * @Flow\Inject
     * @var ContentContextService
     */
    protected $contentContextService;

    /**
     * @Flow\Inject
     * @var PropertyMapper
     */
    protected $propertyMapper;

    /**
     * Search all properties for given $term
     *
     * TODO: Implement a better search when Flow offer the possibility
     *
     * @param string $term
     * @param array $searchNodeTypes
     * @param Context $context
     * @param NodeInterface $startingPoint
     * @return array <\TYPO3\TYPO3CR\Domain\Model\NodeInterface>
     */
    public function findByProperties($term, array $searchNodeTypes, Context $context = null, NodeInterface $startingPoint = null)
    {
        if (strlen($term) === 0) {
            throw new \InvalidArgumentException('"term" cannot be empty: provide a term to search for.', 1421329285);
        }

        if ($context === null) {
            $context = $this->contentContextService->getContentContext();
        }

        $searchResult = [];
        $nodeTypeFilter = implode(',', $searchNodeTypes);
        $nodeDataRecords = $this->nodeDataRepository->findByProperties($term, $nodeTypeFilter, $context->getWorkspace(), $context->getDimensions(), $startingPoint ? $startingPoint->getPath() : null);
        foreach ($nodeDataRecords as $nodeData) {
            $node = $this->nodeFactory->createFromNodeData($nodeData, $context);
            if ($node !== null) {
                $searchResult[$node->getPath()] = $node;
            }
        }

        return $searchResult;
    }

    /**
     * @param string $term
     * @param array $searchNodeTypes
     * @param Context $context
     * @param NodeInterface|null $startingPoint
     * @return mixed
     */
    public function findOneByProperties($term, array $searchNodeTypes, Context $context = null, NodeInterface $startingPoint = null)
    {

        if ($context === null) {
            $context = $this->contentContextService->getContentContext();
        }

        $nodes = $this->findByProperties($term, $searchNodeTypes, $context, $startingPoint);

        if (!empty($nodes)) {
            return reset($nodes);
        }

        return null;
    }

    /**
     * @param string $nodeTypeFilter
     * @param array $contextProperties
     * @return array
     */
    public function findByNodeType($nodeTypeFilter, $contextProperties = [])
    {
        if (is_array($nodeTypeFilter)) {
            $nodeTypeFilter = implode(',', $nodeTypeFilter);
        }

        $context = $this->contentContextService->getContentContext($contextProperties);
        $nodes = [];
        $siteNode = $context->getCurrentSiteNode();
        foreach ($this->nodeDataRepository->findByParentAndNodeTypeRecursively($siteNode->getPath(), $nodeTypeFilter, $context->getWorkspace(), $context->getDimensions()) as $nodeData) {
            $nodes[] = $this->nodeFactory->createFromNodeData($nodeData, $context);
        }

        return $nodes;

    }

    /**
     * @param string $identifier
     * @param array $contextProperties
     * @return NodeInterface
     */
    public function getNodeByIdentifier($identifier, array $contextProperties = [])
    {
        $context = $this->contentContextService->getContentContext($contextProperties);

        return $context->getNodeByIdentifier($identifier);
    }


    /**
     * @param string $string
     * @return NodeInterface
     */
    public function getNodeByNodeString($string)
    {
        return $this->propertyMapper->convert($string, NodeInterface::class);
    }
}
