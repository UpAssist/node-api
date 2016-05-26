<?php
namespace UpAssist\NodeApi\ViewHelpers\Node;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Fluid\Core\ViewHelper\AbstractViewHelper;
use UpAssist\NodeApi\Services\Node\ReadService;

class GetNodeByIdentifierViewHelper extends AbstractViewHelper
{
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

        return $this->nodeReadService->getNodeByIdentifier($nodeIdentifier);
    }
}
