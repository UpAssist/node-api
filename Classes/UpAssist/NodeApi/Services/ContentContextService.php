<?php
namespace UpAssist\NodeApi\Services;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Neos\Domain\Repository\DomainRepository;
use TYPO3\Neos\Domain\Repository\SiteRepository;
use TYPO3\Neos\Domain\Service\ContentContext;
use TYPO3\Neos\Domain\Service\ContentContextFactory;

/**
 * @Flow\Scope("singleton")
 */
class ContentContextService
{

    /**
     * @var ContentContext
     */
    protected $contentContext;

    /**
     * @Flow\Inject
     * @var ContentContextFactory
     */
    protected $contentContextFactory;

    /**
     * @Flow\Inject
     * @var DomainRepository
     */
    protected $domainRepository;

    /**
     * @Flow\Inject
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @return ContentContext
     */
    public function getContentContext()
    {
        if ($this->contentContext instanceof ContentContext) {
            return $this->contentContext;
        }

        $contextProperties = ['workspaceName' => 'live'];

        $currentDomain = $this->domainRepository->findOneByActiveRequest();

        if ($currentDomain !== NULL) {
            $contextProperties['currentSite'] = $currentDomain->getSite();
            $contextProperties['currentDomain'] = $currentDomain;
        } else {
            $contextProperties['currentSite'] = $this->siteRepository->findFirstOnline();
        }

        $this->contentContext = $this->contentContextFactory->create($contextProperties);
        return $this->contentContext;
    }
}