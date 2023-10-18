<?php

namespace Espo\Modules\EsignatureField\Services;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;
use Espo\Modules\EsignatureField\Htmlizer\CustomHtmlizer;

class PrintForEsignature extends \Espo\Core\Templates\Services\Base
{
    protected $fontFace = 'freesans';

    protected $fontSize = 10;

    protected $removeMassFilePeriod = '1 hour';

    protected function init()
    {
        $this->addDependency('fileManager');
        $this->addDependency('acl');
        $this->addDependency('metadata');
        $this->addDependency('serviceFactory');
        $this->addDependency('dateTime');
        $this->addDependency('number');
        $this->addDependency('entityManager');
        $this->addDependency('defaultLanguage');
    }

    protected function getAcl()
    {
        return $this->getInjection('acl');
    }

    protected function getMetadata()
    {
        return $this->getInjection('metadata');
    }

    protected function getServiceFactory()
    {
        return $this->getInjection('serviceFactory');
    }

    protected function getFileManager()
    {
        return $this->getInjection('fileManager');
    }

    public function buildFromTemplate(Entity $entity, Entity $template, $isPortal)
    {
        $entityType = $entity->getEntityType();
        $service = $this->getServiceFactory()->create($entityType);
        $service->loadAdditionalFields($entity);
        if ($template->get('entityType') !== $entityType) {
            throw new Forbidden();
        }
        if (!$this->getAcl()->check($entity, 'read') || !$this->getAcl()->check($template, 'read')) {
            throw new Forbidden();
        }
        $htmlizer = $this->createHtmlizer();
        $output = $this->printEntity($entity, $template, $htmlizer, $isPortal);
        echo $output;
    }

    protected function createHtmlizer()
    {
        return new CustomHtmlizer(
            $this->getFileManager(),
            $this->getInjection('dateTime'),
            $this->getInjection('number'),
            $this->getAcl(),
            $this->getInjection('entityManager'),
            $this->getInjection('metadata'),
            $this->getInjection('defaultLanguage')
        );
    }

    protected function printEntity(Entity $entity, Entity $template, CustomHtmlizer $htmlizer, $isPortal)
    {
        $htmlFooter = '';
        if ($template->get('fontFace')) {
            $fontFace = $template->get('fontFace');
        }
        if ($template->get('printFooter')) {
            $htmlFooter = $htmlizer->render($entity, $template->get('footer'));
        }
        $pageOrientation = 'Portrait';
        if ($template->get('pageOrientation')) {
            $pageOrientation = $template->get('pageOrientation');
        }
        $pageFormat = 'A4';
        if ($template->get('pageFormat')) {
            $pageFormat = $template->get('pageFormat');
        }
        $pageOrientationCode = 'P';
        if ($pageOrientation === 'Landscape') {
            $pageOrientationCode = 'L';
        }

        $headerTemplate = $template->get('header') ? $template->get('header') : '';

        // render page header
        $preHtmlHeader = $htmlizer->render($entity, $headerTemplate);

        // generate and add, if required,  HTML for the top action buttons (close and print)
        $topActionButtonsHtml = '<button title="Close" class="btn btn-default btn-icon-x-wide" id="documentBackButton" type="button" data-action="closeDocumentFullView"><span class="fa fa-times"></span></button>';
        $topActionButtonsHtml .= '<button title="Print" class="btn btn-default btn-icon-x-wide" id="documentPrintButton" type="button" data-action="printDocumentFullView"><span class="fa fa-print"></span></button>';
        if(stripos($preHtmlHeader, $topActionButtonsHtml) === false) {
            $preHtmlHeader = '<p>'.$topActionButtonsHtml.'</p>'.$preHtmlHeader;
        }

        // adjust the header button actions for portal and non-portal access
        if($isPortal == '1') {
            $htmlHeader = str_replace('data-action="closeDocumentFullView"', 'onClick="eSignatureCloseFullPageDocumentAtPortal();"', $preHtmlHeader);
        } else {
            $htmlHeader = str_replace('data-action="closeDocumentFullView"', 'onClick="eSignatureCloseFullPageDocument();"', $preHtmlHeader);
        }
        $htmlHeader = str_replace('data-action="printDocumentFullView"', 'onClick="eSignaturePrintFullPageDocument();"', $htmlHeader);

        // start page body rendering
        $rawTemplateBody = $template->get('body');
        // replace image entryPoint placeholders
        $templateBody = str_replace("@@imageEntryPoint@@", "image", $rawTemplateBody);
        // replace standard handlebars placeholders
        $htmlBody = $htmlizer->render($entity, $templateBody);
        // find and replace signature placeholders
        $startNeedle = '@@sig[';
        $endNeedle = ']/sig@@';
        $signaturePlaceholders = $this->findCustomPlaceholderNames($htmlBody, $startNeedle, $endNeedle);
        foreach($signaturePlaceholders as $field) {
            if($entity->get($field)) {
                $htmlBody = str_replace($startNeedle.$field.$endNeedle, $entity->get($field), $htmlBody);
            } else {
                $eSignatureDiv = '<div class="eSignature" data-field-name="'.$field.'"></div>';
                $htmlBody = str_replace($startNeedle.$field.$endNeedle, $eSignatureDiv, $htmlBody);
            }
        }
        return $htmlHeader.$htmlBody.$htmlFooter;
    }

    public function findCustomPlaceholderNames($haystack, $startNeddle, $endNeedle)
    {
        $lastPos = 0;
        $placeholderNames = array();
        while (($lastPos = strpos($haystack, $startNeddle, $lastPos)) !== false) {
            $placeholderNameStart = $lastPos + strlen($startNeddle);
            $placeholderNameLength = strpos($haystack, $endNeedle, $lastPos) - $placeholderNameStart;
            $placeholderName = substr($haystack, $placeholderNameStart, $placeholderNameLength);
            // avoid repeating field names
            if(!array_search($placeholderName, $placeholderNames)) {
                $placeholderNames[] = $placeholderName;
            }
            $lastPos = $lastPos + strlen($startNeddle);
        }
        return $placeholderNames;
    }

}
