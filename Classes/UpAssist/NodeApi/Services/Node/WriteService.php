<?php
namespace UpAssist\NodeApi\Services\Node;

use TYPO3\Flow\Annotations as Flow;
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

}
