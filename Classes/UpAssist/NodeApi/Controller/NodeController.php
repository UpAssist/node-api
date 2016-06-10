<?php
namespace UpAssist\NodeApi\Controller;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\Node;

/**
 * Class NodeController
 * @package UpAssist\NodeApi\Controller
 */
class NodeController extends \TYPO3\Neos\Service\Controller\NodeController
{

    /**
     *
     */
    public function newAction()
    {
    }

    /**
     * @param Node $referenceNode
     * @param array $nodeData
     * @param string $position
     */
    public function createAction(Node $referenceNode, array $nodeData = [], $position = null)
    {
        parent::createAction($referenceNode, $nodeData, $position);
    }
}
