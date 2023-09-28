<?php

namespace App\Forum\Widgets;

use App\Classes\Template;
use App\Classes\UrlManager;

/**
 * The PaginationWidget class generates a pagination widget with dynamic page links.
 */
class PaginationWidget
{
    public $Template;
    private int $currentPage;
    private int $totalPages;
    private int $maximumResults;
    private string $baseRedirectPath;

    /**
     * Constructor to initialize the PaginationWidget with necessary parameters.
     *
     * @param string $theme            The theme to use for the widget.
     * @param int    $currentPage      The current page number.
     * @param int    $totalPages       The total number of pages.
     * @param int    $maximumResults   The maximum results per page.
     * @param string $baseRedirectPath The base path for page redirects.
     */
    public function __construct(int $currentPage, int $totalPages, int $maximumResults, string $baseRedirectPath)
    {
        $this->currentPage = $currentPage;
        $this->totalPages = $totalPages;
        $this->maximumResults = $maximumResults;
        $this->baseRedirectPath = $baseRedirectPath;
        $this->Template = new Template('pagination', 'pagination');
        $this->Do();
    }

    /**
     * Generate the pagination widget with dynamic page links.
     */
    private function Do()
    {
        // Check if there are pages to display.
        if (ceil($this->totalPages / $this->maximumResults) <= 0)
            return;

        $btnprev = '';

        if ($this->currentPage > 1)
        {
            $btnprevTemplate = new Template('pagination', 'prev_button');
            $btnprevTemplate->AddEntry('{page}', $this->currentPage - 1);
            $btnprevTemplate->Replace();
            $btnprev = $btnprevTemplate->template;
        }

        $startdots = '';

        if ($this->currentPage > 3)
        {
            $startdocsTemplate = new Template('pagination', 'start_dots');
            $startdots = $startdocsTemplate->template;
        }

        $pagesub1 = '';
        $pagesub2 = '';

        if ($this->currentPage - 2 > 0)
        {
            $pagesub2Template = new Template('pagination', 'page_m2');
            $pagesub2Template->AddEntry('{page}', $this->currentPage - 2);
            $pagesub2Template->Replace();
            $pagesub2 = $pagesub2Template->template;
        }

        if ($this->currentPage - 1 > 0)
        {
            $pagesub1Template = new Template('pagination', 'page_m1');
            $pagesub1Template->AddEntry('{page}', $this->currentPage - 1);
            $pagesub1Template->Replace();
            $pagesub2 = $pagesub1Template->template;
        }

        $currentPageTemplate = new Template('pagination', 'page_current');
        $currentPageTemplate->AddEntry('{page}', $this->currentPage);
        $currentPageTemplate->Replace();

        $pageadd1 = '';
        $pageadd2 = '';

        if ($this->currentPage + 1 < ceil($this->totalPages / $this->maximumResults) + 1)
        {
            $pageadd1Template = new Template('pagination', 'page_m1');
            $pageadd1Template->AddEntry('{page}', $this->currentPage + 1);
            $pageadd1Template->Replace();
            $pageadd1 = $pageadd1Template->template;
        }

        if ($this->currentPage + 2 < ceil($this->totalPages / $this->maximumResults) + 1)
        {
            $pageadd2Template = new Template('pagination', 'page_m1');
            $pageadd2Template->AddEntry('{page}', $this->currentPage + 2);
            $pageadd2Template->Replace();
            $pageadd2 = $pageadd2Template->template;
        }

        $enddots = '';

        if ($this->currentPage < ceil($this->totalPages / $this->maximumResults) - 2)
        {
            $enddocsTemplate = new Template('pagination', 'end_dots');
            $enddocsTemplate->AddEntry('{page}', ceil($this->totalPages / $this->maximumResults));
            $enddocsTemplate->Replace();
            $enddots = $enddocsTemplate->template;
        }

        $btnnext = '';

        if ($this->currentPage < ceil($this->totalPages / $this->maximumResults))
        {
            $btnnextTemplate = new Template('pagination', 'next_button');
            $btnnextTemplate->AddEntry('{page}', $this->currentPage + 1);
            $btnnextTemplate->Replace();
            $btnnext = $btnnextTemplate->template;
        }

        $this->Template->AddEntry('{prev_button}', $btnprev);
        $this->Template->AddEntry('{start_dots}', $startdots);
        $this->Template->AddEntry('{page_prev_m2}', $pagesub2);
        $this->Template->AddEntry('{page_prev_m1}', $pagesub1);
        $this->Template->AddEntry('{current_page}', $currentPageTemplate->template);
        $this->Template->AddEntry('{page_next_m1}', $pageadd1);
        $this->Template->AddEntry('{page_next_m2}', $pageadd2);
        $this->Template->AddEntry('{end_dots}', $enddots);
        $this->Template->AddEntry('{next_button}', $btnnext);
        $this->Template->Replace();

        $this->Template->AddEntry('{server_url}', UrlManager::GetPath() . $this->baseRedirectPath);
        $this->Template->Replace();
    }
}

?>