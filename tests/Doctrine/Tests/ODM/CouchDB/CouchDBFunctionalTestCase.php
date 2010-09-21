<?php

namespace Doctrine\Tests\ODM\CouchDB;

abstract class CouchDBFunctionalTestCase extends \PHPUnit_Framework_TestCase
{
    private $httpClient = null;

    private $useModelSet = null;

    public function useModelSet($name)
    {
        $this->useModelSet = $name;
    }

    /**
     * @return \Doctrine\ODM\CouchDB\HTTP\Client
     */
    public function getHttpClient()
    {
        if ($this->httpClient === null) {
            $this->httpClient = new \Doctrine\ODM\CouchDB\HTTP\SocketClient();
        }
        return $this->httpClient;
    }

    public function getTestDatabase()
    {
        return TestUtil::getTestDatabase();
    }

    public function createDocumentManager()
    {
        $database = $this->getTestDatabase();
        $httpClient = $this->getHttpClient();

        $httpClient->request('DELETE', '/' . $database);
        $resp = $httpClient->request('PUT', '/' . $database);

        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $config->setDefaultDB($database);
        $config->setProxyDir(\sys_get_temp_dir());

        $dm = \Doctrine\ODM\CouchDB\DocumentManager::create($httpClient, $config);

        $cmf = $dm->getClassMetadataFactory();
        if ($this->useModelSet == 'cms') {
            $cm = new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata('Doctrine\Tests\Models\CMS\CmsUser');
            $cm->mapId(array('name' => 'id'));
            $cm->mapProperty(array('name' => 'username'));
            $cm->mapProperty(array('name' => 'name'));
            $cm->mapProperty(array('name' => 'status'));
            $cmf->setMetadataFor($cm);

            $cm = new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata('Doctrine\Tests\Models\CMS\CmsArticle');
            $cm->mapId(array('name' => 'id'));
            $cm->mapProperty(array('name' => 'topic'));
            $cm->mapProperty(array('name' => 'text'));
            $cm->mapManyToOne(array('name' => 'user', 'targetDocument' => 'Doctrine\Tests\Models\CMS\CmsUser'));
            $cmf->setMetadataFor($cm);
        }

        return $dm;
    }
}