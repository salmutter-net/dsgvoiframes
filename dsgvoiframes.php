<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.dsgvoiframes
 *
 * @copyright   (C) 2022 salmutter.net
 * @license      Commercial Plugin
 */
defined( '_JEXEC' ) or die;

use Joomla\CMS\Factory;
use Joomla\String\StringHelper;

require __DIR__ . '/tmpl/simple_html_dom.php';


class PlgContentDsgvoiframes extends JPlugin {

    public function onContentPrepare( $context, &$row, &$params, $page = 0 ) {
        // Don't run this plugin when the content is being indexed
        if ( $context === 'com_finder.indexer' ) {
            return true;
        }
        if ( is_object( $row ) ) {
            return $this->_parseHtml( $row->text, $params );
        }
        return $this->_parseHtml( $row, $params );
    }

    protected function _parseHtml( &$htmlString, &$params ) {
        /*
         * Check for presence of {dsgvoiframe=off} which is explicits disables this
         * bot for the item.
         */
        if ( StringHelper::strpos( $htmlString, '{dsgvoiframe=off}' ) !== false ) {
            $htmlString = StringHelper::str_ireplace( '{dsgvoiframe=off}', '', $htmlString );
            return true;
        }

        // Simple performance check to determine whether bot should process further.
        if ( StringHelper::strpos( $htmlString, '<iframe' ) === false ) {
            return true;
        }

        /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->addInlineStyle(file_get_contents( __DIR__ . '/dsgvoiframes.css' ), ['name' => 'dsgvoiframes']);

        /**
         * @var $html       simple_html_dom
         * @var $element    simple_html_dom_node
         */
        $availableIcons = [
            'facebook',
            'google',
            'instagram',
            'twitter',
            'youtube',
            'x',
        ];
        $iconFolder = JPATH_ROOT . '/plugins/content/dsgvoiframes/tmpl/icons/';
        $html = str_get_html( $htmlString );
        foreach ( $html->find( 'iframe' ) as $element ) {
            $iconFile = 'other.svg';
            $random = uniqid('', 0);

            $iframeSrc = $element->src;
            $iframeTitle = $element->title;
            $iframeWidth = $element->width . ( strpos($element->width, '%') ? '' : 'px');
            $iframeHeight = $element->height . ( strpos($element->height, '%') ? '' : 'px');
            $iframeSrcUrl = str_replace( 'www.', '', parse_url( $iframeSrc, PHP_URL_HOST ) );
            $urlCommonParts =  array_intersect( $availableIcons, explode( '.', $iframeSrcUrl ) );
            if ( !empty($urlCommonParts) ) {
                $urlCommonPart = array_shift($urlCommonParts);
                $iconFile = $urlCommonPart . '.svg';
            }
            $iconPath = $iconFolder . $iconFile;
            $wa->addInlineStyle('
                #dsgvo-iframe-container-'.$random.' {
                    width: '.$iframeWidth.';
                    height: '.$iframeHeight.';
                }');

            $replacement = <<<HTML
                <div id="dsgvo-iframe-container-$random" class="dsgvo-iframe-container fake-iframe">
                    <img width="60" height="auto" src="$iconPath" alt="">
                    <p>Mit dem Klick auf den folgenden Button lade ich bewusst Inhalte von der externen Website: $iframeSrcUrl.</p>
                    <button class="button">
                        Inhalte von $iframeSrcUrl laden
                    </button>
                </div>
            HTML;

            $wa->addInlineScript('
                window.addEventListener("load", function() {
                    const dsgvoIframeContainer = document.getElementById("dsgvo-iframe-container-'.$random.'");
                    const button = dsgvoIframeContainer.querySelector("button");
                    button.addEventListener("click", function(event) {
                        event.preventDefault();
                        const iframe = document.createElement("iframe");
                        iframe.setAttribute("src", "'.$iframeSrc.'");
                        iframe.style.width = "'.$iframeWidth.'";
                        iframe.style.height = "'.$iframeHeight.'";
                        iframe.title = "'.$iframeTitle.'";
                        document.getElementById("dsgvo-iframe-container-'.$random.'").after(iframe);
                        document.getElementById("dsgvo-iframe-container-'.$random.'").remove();
                    })
                })
            ');

            $element->outertext = $replacement;

        }
        $htmlString = $html;
        return true;
    }
}
