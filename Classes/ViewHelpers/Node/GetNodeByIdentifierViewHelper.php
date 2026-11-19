<?php
namespace UpAssist\NodeApi\ViewHelpers\Node;

use Neos\Flow\Annotations as Flow;
use Neos\FluidAdaptor\Core\ViewHelper\AbstractViewHelper;
use UpAssist\NodeApi\Services\Node\ReadService;

class GetNodeByIdentifierViewHelper extends AbstractViewHelper
{
    
	/**
	 * NOTE: This property has been introduced via code migration to ensure backwards-compatibility.
	 * @see AbstractViewHelper::isOutputEscapingEnabled()
	 * @var boolean
	 */
	protected $escapeOutput = FALSE;
    /**
     * @Flow\Inject
     * @var ReadService
     */
    protected $nodeReadService;

    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('nodeIdentifier', 'string', 'Identifier of the node to fetch; falls back to the tag content', false, null);
    }

    public function render()
    {
        $nodeIdentifier = $this->arguments['nodeIdentifier'];
        if ($nodeIdentifier === null) {
            $nodeIdentifier = $this->renderChildren();
        }

        $node = $this->nodeReadService->getNodeByIdentifier($nodeIdentifier);

        return $node;
    }
}
