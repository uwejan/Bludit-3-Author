<?php

class pluginAuthor extends Plugin {

    private $pagesFound = array();
    private $numberOfItems = 0;

    public function init()
    {
        // Fields and default values for the database of this plugin
        $this->dbFields = array(
            'label'=>'Author',
            'wordsToCachePerPage'=>800
        );
    }


    public function install($position=0)
    {
        parent::install($position);
        return $this->createCache();
    }

    // Method called when the user click on button save in the settings of the plugin
    public function post()
    {
        parent::post();
        $this->createCache();
    }

    public function afterPageCreate()
    {
        $this->createCache();
    }

    public function afterPageModify()
    {
        $this->createCache();
    }

    public function afterPageDelete()
    {
        $this->createCache();
    }

    public function beforeAll()
    {
        // Check if the URL match with the webhook
        $webhook = 'author';
        if ($this->webhook($webhook, false, false)) {
            global $site;
            global $url;

            // Change the whereAmI to avoid load pages in the rule 69.pages
            // This is only for performance propose
            $url->setWhereAmI('author');

            // Get the string to search from the URL
            $stringToSearch = $this->webhook($webhook, true, false);
            $stringToSearch = trim($stringToSearch, '/');

            // Search the string in the cache and get all pages with matches
            $list = $this->search($stringToSearch);

            $this->numberOfItems = count($list);

            // Split the content in pages
            // The first page number is 1, so the real is 0
            $realPageNumber = $url->pageNumber() - 1;
            $itemsPerPage = $site->itemsPerPage();
            $chunks = array_chunk($list, $itemsPerPage);
            if (isset($chunks[$realPageNumber])) {
                $this->pagesFound = $chunks[$realPageNumber];
            }
        }
    }

    public function paginator()
    {
        $webhook = 'author';
        if ($this->webhook($webhook, false, false)) {
            global $numberOfItems;
            $numberOfItems = $this->numberOfItems;
        }
    }

    public function beforeSiteLoad()
    {
        $webhook = 'author';
        if ($this->webhook($webhook, false, false)) {

            global $url;
            global $WHERE_AM_I;
            $WHERE_AM_I = 'author';

            // Get the pre-defined variable from the rule 69.pages.php
            // We change the content to show in the website
            global $content;
            $content = array();
            foreach ($this->pagesFound as $pageKey) {
                try {
                    $page = new Page($pageKey);
                    array_push($content, $page);
                } catch (Exception $e) {
                    // continue
                }
            }
        }
    }

    // Generate the cache file
    // This function is necessary to call it when you create, edit or remove content
    private function createCache()
    {
        // Get all pages published
        global $pages;
        $list = $pages->getList($pageNumber = 1, $numberOfItems = -1, $onlyPublished = true);

        $cache = array();
        foreach ($list as $pageKey) {
            $page = buildPage($pageKey);

            // Process content
            $words = $this->getValue('wordsToCachePerPage') * 5; // Asumming avg of characters per word is 5
            $content = $page->content();
            $content = Text::removeHTMLTags($content);
            $content = Text::truncate($content, $words, '');

            // Include the page to the cache
            $cache[$pageKey]['author'] = $page->username();
            $cache[$pageKey]['key'] = $pageKey;
        }

        // Generate JSON file with the cache
        $json = json_encode($cache);
        return file_put_contents($this->cacheFile(), $json, LOCK_EX);
    }

    // Returns the absolute path of the cache file
    private function cacheFile()
    {
        return $this->workspace().'cache.json';
    }

    // Search text inside the cache
    // Returns an array with the pages keys related to the text
    // The array is sorted by score
    private function search($text)
    {
        // Read the cache file
        $json = file_get_contents($this->cacheFile());
        $cache = json_decode($json, true);

        $found = array();
        foreach ($cache as $page) {
            $score = 0;
            if (Text::stringContains($page['author'], $text, false)) {
                $score += 10;
            }
            if ($score>0) {
                $found[$page['key']] = $score;
            }
        }

        // Sort array by the score, from max to min
        arsort($found);
        // Returns only the keys of the array contained the page key
        return array_keys($found);
    }

}
