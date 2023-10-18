<?php

namespace Espo\Modules\EsignatureField\EntryPoints;

use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\BadRequest;

class PrintForEsignature extends \Espo\Core\EntryPoints\Base
{
    public static $authRequired = true;

    public function run()
    {

        if (empty($_GET['entityId']) || empty($_GET['entityType']) || empty($_GET['templateId'])) {
            throw new BadRequest();
        }
        $entityId = $_GET['entityId'];
        $entityType = $_GET['entityType'];
        $templateId = $_GET['templateId'];
        $isPortal = $_GET['isPortal'];

        $entity = $this->getEntityManager()->getEntity($entityType, $entityId);
        $template = $this->getEntityManager()->getEntity('Template', $templateId);

        if (!$entity || !$template) {
            throw new NotFound();
        }

        $this->getContainer()->get('serviceFactory')->create('PrintForEsignature')->buildFromTemplate($entity, $template, $isPortal);

        exit;
    }
}
