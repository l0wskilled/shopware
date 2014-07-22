<?php
namespace Emotion;

use Behat\Mink\Element\NodeElement;
use SensioLabs\Behat\PageObjectExtension\PageObject\Element;
use SensioLabs\Behat\PageObjectExtension\PageObject\Page, Behat\Mink\Exception\ResponseTextException,
    Behat\Behat\Context\Step;

class Listing extends Page
{
    /**
     * @var string $path
     */
    protected $path = '/listing/index/sCategory/{sCategory}/sSupplier/{sSupplier}?sPage={sPage}&sTemplate={sTemplate}&sPerPage={sPerPage}&sSort={sSort}';

    public $cssLocator = array(
        'view' => array(
            'table' => 'a.table-view',
            'list' => 'a.list-view'),
        'active' => '.active',
        'filterContainer' => 'div.filter_properties > div',
        'filterCloseLinks' => 'div.slideContainer > ul > li.close > a',
        'filterGroups' => 'div > div:not(.slideContainer)',
        'filterProperties' => 'div.slideContainer:nth-of-type(%d) > ul > li > a',
        'articleBox' => 'div.artbox',
        'articlePrice' => 'div.listing div.artbox:nth-of-type(%d) p.price',
        'listingBox' => 'div.listing'
    );

    protected $viewSwitchCount = 2;
    protected $filterGroupsHasBrackets = true;
    protected $filterPropertyFactor = 2;

    /**
     * Opens the listing page
     * @param $params
     */
    public function openListing($params)
    {
        $parameters = array();

        foreach ($params as $param) {
            $parameters[$param['parameter']] = $param['value'];
        }

        if (empty($parameters['sCategory'])) {
            $parameters['sCategory'] = 3;
        }

        if (empty($parameters['sPage'])) {
            $parameters['sPage'] = 1;
        }

        $parameters['sSupplier'] = isset($parameters['sSupplier']) ? $parameters['sSupplier'] : '';
        $parameters['sTemplate'] = isset($parameters['sTemplate']) ? $parameters['sTemplate'] : '';
        $parameters['sPerPage'] = isset($parameters['sPerPage']) ? $parameters['sPerPage'] : '';
        $parameters['sSort'] = isset($parameters['sSort']) ? $parameters['sSort'] : '';

        $this->open($parameters);
    }

    /**
     * Checks the price of an article on an given position
     * @param $position
     * @param $price
     * @throws \Behat\Mink\Exception\ResponseTextException
     */
    public function checkPrice($position, $price)
    {
        $locators = array('articlePrice' => $position);
        $elements = \Helper::findElements($this, $locators);

        $check = \Helper::toFloat(array($elements['articlePrice']->getText(), $price));
        $result = \Helper::checkArray(array($check));

        if ($result !== true) {
            $message = sprintf(
                'The price of article on position %s (%s €) is different from %s €!',
                $position,
                money_format('%.2n', $check[0]),
                money_format('%.2n', $check[1])
            );
            throw new ResponseTextException($message, $this->getSession());
        }
    }

    /**
     * Sets the article filter
     * @param array $properties
     * @throws \Behat\Mink\Exception\ResponseTextException
     */
    public function filter($properties)
    {
        $locators = array('filterContainer');
        $elements = \Helper::findElements($this, $locators);

        $filterContainer = $elements['filterContainer'];

        //Reset all filters
        $locators = array('filterCloseLinks');
        $elements = \Helper::findElements($filterContainer, $locators, $this->cssLocator, true, false);

        if (isset($elements['filterCloseLinks'])) {
            $closeLinks = array_reverse($elements['filterCloseLinks']);
            foreach ($closeLinks as $closeLink) {
                $closeLink->click();
            }
        }

        //Set new filters
        $locators = array('filterGroups');
        $elements = \Helper::findElements($filterContainer, $locators, $this->cssLocator, true);

        $filterGroups = array();

        foreach ($elements['filterGroups'] as $filterGroup) {
            $filterGroups[] = $this->getElementName($filterGroup, $this->filterGroupsHasBrackets);
        }

        foreach ($properties as $property) {
            $filterKey = array_search($property['filter'], $filterGroups, true);

            if ($filterKey === false) {
                $message = sprintf('The filter "%s" was not found!', $property['filter']);
                throw new ResponseTextException($message, $this->getSession());
            }

            $success = $this->setFilterProperty($filterKey, $property['value'], $filterContainer);

            if (!$success) {
                $message = sprintf('The value "%s" was not found for filter "%s"!', $property['value'], $property['filter']);
                throw new ResponseTextException($message, $this->getSession());
            }
        }
    }

    /**
     * Helper function to set a filter
     * @param integer $filterKey
     * @param string $value
     * @param NodeElement $filterContainer
     * @return bool
     */
    protected function setFilterProperty($filterKey, $value, $filterContainer)
    {
        $filterKey = ($filterKey + 1) * $this->filterPropertyFactor;

        $locators = array('filterProperties' => $filterKey);
        $elements = \Helper::findElements($filterContainer, $locators, $this->cssLocator, true);

        foreach ($elements['filterProperties'] as $property) {
            $propertyName = $this->getElementName($property);

            if ($propertyName === $value) {
                $property->click();
                return true;
            }
        }

        return false;
    }

    /**
     * Helper function to get the displayed name of a filter or filter property
     * @param NodeElement $element
     * @param bool $hasBrackets
     * @return string
     */
    protected function getElementName($element, $hasBrackets = true)
    {
        $name = $element->getText();
        $name = trim($name);

        if (empty($name)) {
            $name = $element->getAttribute('title');
            $name = trim($name);

            return $name;
        }

        if($hasBrackets) {
            $length = strrpos($name, ' ');
            $name = substr($name, 0, $length);
        }

        return $name;
    }

    /**
     * Counts the articles in the listing
     * If the number is not equal to $count, the helper function will throw an exception $message.
     * @param int $count
     */
    public function countArticles($count = 0)
    {
        $result = \Helper::countElements($this, $this->cssLocator['articleBox'], $count);

        if ($result !== true) {
            $message = sprintf('There are %d articles in the listing (should be %d)', $result, $count);
            \Helper::throwException(array($message));
        }
    }

    /**
     * Checks the view-method of the listing. Only $view have to be active!
     * @param $view
     */
    public function checkView($view)
    {
        foreach ($this->cssLocator['view'] as $key => $viewCssLocator) {
            $message = sprintf('The %s-view is active! (should be %s-view)', $key, $view);
            $count = 0;

            if ($key === $view) {
                $message = sprintf('The %s-view is not active!', $view);
                $count = $this->viewSwitchCount;
            }

            $result = \Helper::countElements($this, $viewCssLocator . $this->cssLocator['active'], $count);

            if ($result !== true) {
                \Helper::throwException(array($message));
            }
        }
    }

    /**
     * Checks, whether an article is in the listing or not, is $negation is true, it checks whether an article is NOT in the listing
     *
     * @param $name
     * @param bool $negation
     */
    public function checkListing($name, $negation = false)
    {
        $result = $this->isArticleInListing($name);

        if ($negation) {
            $result = !$result;
        }

        if (!$result) {
            $message = sprintf(
                'The article "%s" is%s in the listing, but should%s.',
                $name,
                ($negation) ? '' : ' not',
                ($negation) ? ' not' : ''
            );
            \Helper::throwException(array($message));
        }
    }

    private function isArticleInListing($name)
    {
        $locator = array('listingBox');
        $elements = \Helper::findElements($this, $locator);

        /** @var Element $listingBox */
        $listingBox = $elements['listingBox'];
        return $listingBox->hasLink($name);
    }

    /**
     * @param $button
     * @param int $position
     */
    public function clickMultipleActionButton($button, $position = 1)
    {
        $language = $this->getElement('LanguageSwitcher')->getCurrentLanguage();

        /** @var \MultipleElement $articleBoxes */
        $articleBoxes = $this->getElement('ArticleBox');
        $articleBoxes->setParent($this);

        /** @var \Emotion\ArticleBox $articleBox */
        $articleBox = $articleBoxes->setInstance($position);
        $articleBox->clickActionLink($button, $language);
    }
}