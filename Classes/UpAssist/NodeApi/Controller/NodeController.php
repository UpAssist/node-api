<?php
namespace UpAssist\NodeApi\Controller;

use Neos\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\Node;

/**
 * Class NodeController
 * @package UpAssist\NodeApi\Controller
 */
class NodeController extends \Neos\Neos\Service\Controller\NodeController
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
