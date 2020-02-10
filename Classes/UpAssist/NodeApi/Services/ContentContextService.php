<?php
namespace UpAssist\NodeApi\Services;

use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Repository\DomainRepository;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\Neos\Domain\Service\ContentContextFactory;

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
     * @param array $contextProperties
     * @return ContentContext
     */
    public function getContentContext(array $contextProperties = [])
    {
        if ($this->contentContext instanceof ContentContext) {
            return $this->contentContext;
        }

        $contextPropertiesArray = ['workspaceName' => 'live'];
        $contextProperties = \Neos\Utility\Arrays::arrayMergeRecursiveOverrule($contextPropertiesArray, $contextProperties);

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