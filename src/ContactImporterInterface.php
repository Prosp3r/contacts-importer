<?php
namespace ContactImporter;

interface ContactImporterInterface
{
    const VERSION = '1.0.0';

    /**
     * @return GenericContact[]
     */
    public function getContacts();
}
