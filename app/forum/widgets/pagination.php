<?php

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
    private string $theme;

    /**
     * Constructor to initialize the PaginationWidget with necessary parameters.
     *
     * @param string $theme            The theme to use for the widget.
     * @param int    $currentPage      The current page number.
     * @param int    $totalPages       The total number of pages.
     * @param int    $maximumResults   The maximum results per page.
     * @param string $baseRedirectPath The base path for page redirects.
     */
    public function __construct(string $theme, int $currentPage, int $totalPages, int $maximumResults, string $baseRedirectPath)
    {
        $this->theme = $theme;
        $this->currentPage = $currentPage;
        $this->totalPages = $totalPages;
        $this->maximumResults = $maximumResults;
        $this->baseRedirectPath = $baseRedirectPath;
        $this->Template = new Template('./themes/' . $this->theme . '/templates/pagination/pagination.html');
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
            $btnprevTemplate = new Template('./themes/' . $this->theme . '/templates/pagination/prev_button.html');
            $btnprevTemplate->AddEntry('{page}', $this->currentPage - 1);
            $btnprevTemplate->Replace();
            $btnprev = $btnprevTemplate->templ;
        }

        $startdots = '';

        if ($this->currentPage > 3)
        {
            $startdocsTemplate = new Template('./themes/' . $this->theme . '/templates/pagination/start_dots.html');
            $startdots = $startdocsTemplate->templ;
        }

        $pagesub1 = '';
        $pagesub2 = '';

        if ($this->currentPage - 2 > 0)
        {
            $pagesub2Template = new Template('./themes/' . $this->theme . '/templates/pagination/page_m2.html');
            $pagesub2Template->AddEntry('{page}', $this->currentPage - 2);
            $pagesub2Template->Replace();
            $pagesub2 = $pagesub2Template->templ;
        }

        if ($this->currentPage - 1 > 0)
        {
            $pagesub1Template = new Template('./themes/' . $this->theme . '/templates/pagination/page_m1.html');
            $pagesub1Template->AddEntry('{page}', $this->currentPage - 1);
            $pagesub1Template->Replace();
            $pagesub2 = $pagesub1Template->templ;
        }

        $currentPageTemplate = new Template('./themes/' . $this->theme . '/templates/pagination/page_current.html');
        $currentPageTemplate->AddEntry('{page}', $this->currentPage);
        $currentPageTemplate->Replace();

        $pageadd1 = '';
        $pageadd2 = '';

        if ($this->currentPage + 1 < ceil($this->totalPages / $this->maximumResults) + 1)
        {
            $pageadd1Template = new Template('./themes/' . $this->theme . '/templates/pagination/page_m1.html');
            $pageadd1Template->AddEntry('{page}', $this->currentPage + 1);
            $pageadd1Template->Replace();
            $pageadd1 = $pageadd1Template->templ;
        }

        if ($this->currentPage + 2 < ceil($this->totalPages / $this->maximumResults) + 1)
        {
            $pageadd2Template = new Template('./themes/' . $this->theme . '/templates/pagination/page_m1.html');
            $pageadd2Template->AddEntry('{page}', $this->currentPage + 2);
            $pageadd2Template->Replace();
            $pageadd2 = $pageadd2Template->templ;
        }

        $enddots = '';

        if ($this->currentPage < ceil($this->totalPages / $this->maximumResults) - 2)
        {
            $enddocsTemplate = new Template('./themes/' . $this->theme . '/templates/pagination/end_dots.html');
            $enddocsTemplate->AddEntry('{page}', ceil($this->totalPages / $this->maximumResults));
            $enddocsTemplate->Replace();
            $enddots = $enddocsTemplate->templ;
        }

        $btnnext = '';

        if ($this->currentPage < ceil($this->totalPages / $this->maximumResults))
        {
            $btnnextTemplate = new Template('./themes/' . $this->theme . '/templates/pagination/next_button.html');
            $btnnextTemplate->AddEntry('{page}', $this->currentPage + 1);
            $btnnextTemplate->Replace();
            $btnnext = $btnnextTemplate->templ;
        }

        $this->Template->AddEntry('{prev_button}', $btnprev);
        $this->Template->AddEntry('{start_dots}', $startdots);
        $this->Template->AddEntry('{page_prev_m2}', $pagesub2);
        $this->Template->AddEntry('{page_prev_m1}', $pagesub1);
        $this->Template->AddEntry('{current_page}', $currentPageTemplate->templ);
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