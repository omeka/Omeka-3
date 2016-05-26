<?php
namespace Omeka\Settings;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\Site;
use Omeka\Service\Exception;

class SiteSettings extends AbstractSettings
{
    /**
     * @var int
     */
    protected $siteId;

    /**
     * Set the site entity from which to get settings.
     *
     * @param Site|SiteRepresentation $site
     */
    public function setSite($site)
    {
        if ($site instanceof Site) {
            $this->siteId = $site->getId();
        } else if ($site instanceof SiteRepresentation) {
            $this->siteId = $site->id();
        } else {
            $this->siteId = null;
        }
        $this->cache=null;
    }

    protected function setCache()
    {
         if (!$this->siteId) {
            throw new Exception\RuntimeException('Cannot use site settings when no site is set');
        }
        $conn = $this->getConnection();
        $settings = $conn->fetchAll('SELECT * FROM site_setting WHERE site_id = ?', [$this->siteId]);
        foreach ($settings as $setting) {
            $this->cache[$setting['id']] = $conn->convertToPHPValue($setting['value'], 'json_array');
        }
    }

    protected function setSetting($id, $value)
    {
        if (!$this->siteId) {
            throw new Exception\RuntimeException('Cannot use site settings when no site is set');
        }

        $conn = $this->getConnection();
        $siteId = $this->siteId;
        $setting = $conn->fetchAssoc(
            'SELECT * FROM site_setting WHERE id = ? AND site_id = ?',
            [$id, $siteId]
        );
        if ($setting) {
            $conn->update('site_setting', ['value' => $value],
                ['id' => $id, 'site_id' => $siteId], ['json_array']);
        } else {
            $conn->insert('site_setting', [
                'value' => $value,
                'site_id' => $siteId,
                'id' => $id,
            ], ['json_array', \PDO::PARAM_INT]);
        }
    }

    protected function deleteSetting($id)
    {
        if (!$this->siteId) {
            throw new Exception\RuntimeException('Cannot use site settings when no site is set');
        }

        $this->getConnection()->delete('site_setting', [
            'site_id' => $this->siteId,
            'id' => $id,
        ], [\PDO::PARAM_INT]);
    }
}
