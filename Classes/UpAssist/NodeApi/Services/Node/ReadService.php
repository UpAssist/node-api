<?php

namespace UpAssist\NodeApi\Services\Node;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Mvc\Controller\ControllerContext;
use TYPO3\Flow\Mvc\Exception\NoMatchingRouteException;
use TYPO3\Flow\Property\PropertyMapper;
use TYPO3\Neos\Domain\Service\ContentContext;
use TYPO3\Neos\Exception;
use TYPO3\Neos\Service\LinkingService;
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
     * @Flow\Inject
     * @var LinkingService
     */
    protected $linkingService;

    /**
     * Search all nodes for a given term
     *
     * @param string $term
     * @param array $searchNodeTypes
     * @param Context|null $context
     * @param NodeInterface|null $startingPoint
     * @return array
     * @throws \TYPO3\TYPO3CR\Exception\NodeConfigurationException
     */
    public function findByProperties($term, array $searchNodeTypes, Context $context = null, NodeInterface $startingPoint = null)
    {
        if (empty($term) || $term == '') {
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
     * @param Context|null $context
     * @param NodeInterface|null $startingPoint
     * @return mixed|null
     * @throws \TYPO3\TYPO3CR\Exception\NodeConfigurationException
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
     * @param $nodeTypeFilter
     * @param array $contextProperties
     * @param ContentContext|null $context
     * @return array
     * @throws \TYPO3\TYPO3CR\Exception\NodeConfigurationException
     */
    public function findByNodeType($nodeTypeFilter, $contextProperties = [], ContentContext $context = null)
    {
        if (is_array($nodeTypeFilter)) {
            $nodeTypeFilter = implode(',', $nodeTypeFilter);
        }

        if ($context === null) {
            $context = $this->contentContextService->getContentContext($contextProperties);
        }

        $nodes = [];
        $siteNode = $context->getCurrentSiteNode();
        foreach ($this->nodeDataRepository->findByParentAndNodeTypeRecursively($siteNode->getPath(), $nodeTypeFilter, $context->getWorkspace(), $context->getDimensions()) as $nodeData) {
            $nodes[] = $this->nodeFactory->createFromNodeData($nodeData, $context);
        }

        return $nodes;

    }

    /**
     * @param $identifier
     * @param array $contextProperties
     * @param ContentContext|null $context
     * @return NodeInterface
     */
    public function getNodeByIdentifier($identifier, array $contextProperties = [], ContentContext $context = null)
    {
        if ($context === null) {
            $context = $this->contentContextService->getContentContext($contextProperties);
        }

        return $context->getNodeByIdentifier($identifier);
    }


    /**
     * @param string $string
     * @return mixed
     * @throws \TYPO3\Flow\Property\Exception
     * @throws \TYPO3\Flow\Security\Exception
     */
    public function getNodeByNodeString($string)
    {
        return $this->propertyMapper->convert($string, NodeInterface::class);
    }

    /**
     *
     * @param ControllerContext $controllerContext
     * @param NodeInterface $node
     * @return string
     * @throws \TYPO3\Neos\Exception
     */
    public function getNodeUri($controllerContext, $node)
    {
        try {
            return $this->linkingService->createNodeUri(
                $controllerContext,
                $node
            );
        } catch (Exception $exception) {
//            $this->systemLogger->logException($exception);
        }

        return '';
    }
}
