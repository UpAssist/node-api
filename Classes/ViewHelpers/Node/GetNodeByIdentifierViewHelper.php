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

    public function render($nodeIdentifier = null)
    {
        if ($nodeIdentifier === null) {
            $nodeIdentifier = $this->renderChildren();
        }

        $node = $this->nodeReadService->getNodeByIdentifier($nodeIdentifier);

        return $node;
    }
}
