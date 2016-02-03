<?php
namespace UpAssist\NodeApi\Services\Node;

use Doctrine\ORM\EntityManagerInterface;
use TYPO3\Eel\Context;
use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Flow\Property\PropertyMapper;
use TYPO3\Neos\Domain\Service\ContentContext;
use TYPO3\Neos\Service\LinkingService;
use TYPO3\Neos\Service\UserService;
use TYPO3\TYPO3CR\Domain\Factory\NodeFactory;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Domain\Repository\NodeDataRepository;
use TYPO3\TYPO3CR\Domain\Service\ContextFactory;
use UpAssist\NodeApi\Services\ContentContextService;
use UpAssist\NodeApi\Utilities\DimensionUtility;

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
     * @param ContentContext $context
     * @param NodeInterface $startingPoint
     * @return array <\TYPO3\TYPO3CR\Domain\Model\NodeInterface>
     */
    public function findByProperties($term, array $searchNodeTypes, Context $context, NodeInterface $startingPoint = null)
    {
        if (strlen($term) === 0) {
            throw new \InvalidArgumentException('"term" cannot be empty: provide a term to search for.', 1421329285);
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
     * @param array $nodeTypeFilter
     * @return array
     */
    public function findByNodeType($nodeTypeFilter)
    {
        if (is_array($nodeTypeFilter)) {
            $nodeTypeFilter = implode(',', $nodeTypeFilter);
        }

        $context = $this->contentContextService->getContentContext();
        $nodes = [];
        $siteNode = $context->getCurrentSiteNode();
        foreach ($this->nodeDataRepository->findByParentAndNodeTypeRecursively($siteNode->getPath(), $nodeTypeFilter, $context->getWorkspace()) as $nodeData) {
            $nodes[] = $this->nodeFactory->createFromNodeData($nodeData, $context);
        }

        return $nodes;

    }

    /**
     * @param string $identifier
     * @return NodeInterface
     */
    public function getNodeByIdentifier($identifier)
    {
        $context = $this->contentContextService->getContentContext();
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
