<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ODM\CouchDB\Mapping\Driver;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadata,
    SimpleXmlElement,
    Doctrine\ODM\CouchDB\Mapping\MappingException;

/**
 * XmlDriver is a metadata driver that enables mapping through XML files.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.org
 * @since       1.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 */
class XmlDriver extends AbstractFileDriver
{
    /**
     * The file extension of mapping documents.
     *
     * @var string
     */
    protected $fileExtension = '.dcm.xml';

    /**
     * {@inheritdoc}
     */
    public function loadMetadataForClass($className, ClassMetadata $class)
    {
        /* @var $xmlRoot SimpleXMLElement */
        $xmlRoot = $this->getElement($className);
        
        if ($xmlRoot->getName() == "document") {
            $class->setCustomRepositoryClass(
                isset($xmlRoot['repository-class']) ? (string)$xmlRoot['repository-class'] : null
            );
            
            if (isset($xmlRoot['indexed']) && $xmlRoot['indexed'] == true) {
                $class->indexed = true;
            }
        } else if ($xmlRoot->getName() == "embedded-document") {
            $class->isEmbeddedDocument = true;
        } else {
            throw MappingException::classIsNotAValidDocument($className);
        }
        
        // Evaluate <field ...> mappings
        if (isset($xmlRoot->field)) {
            foreach ($xmlRoot->field as $fieldMapping) {
                $class->mapField(array(
                    'fieldName' => (string)$fieldMapping['name'],
                    'jsonName'  => (isset($fieldMapping['json-name'])) ? (string)$fieldMapping['json-name'] : null,
                    'indexed'   => (isset($fieldMapping['indexed'])) ? (bool)$fieldMapping['indexed'] : false,
                    'type'      => (isset($fieldMapping['type'])) ? (string)$fieldMapping['type'] : null,
                    'isVersionField'   => (isset($fieldMapping['version'])) ? true : null,
                ));
            }
        }
        
        // Evaluate <id ..> mappings
        foreach ($xmlRoot->id as $idElement) {
            $class->mapField(array(
                'fieldName' => (string)$idElement['name'],
                'indexed'   => (isset($idElement['indexed'])) ? (bool)$idElement['indexed'] : false,
                'type'      => (isset($idElement['type'])) ? (string)$idElement['type'] : null,
                'id'        => true,
                'strategy'  => (isset($idElement['strategy'])) ? (string)$idElement['strategy'] : null,
            ));
        }
        
        // Evaluate <many-to-one ..> mappings
        if (isset($xmlRoot->{"reference-one"})) {
            foreach ($xmlRoot->{"reference-one"} as $referenceOneElement) {
                $class->mapManyToOne(array(
                    'cascade'           => (isset($referenceManyElement->cascade)) ? $this->getCascadeMode($referenceManyElement->cascade) : 0,
                    'targetDocument'    => (string)$referenceOneElement['target-document'],
                    'fieldName'         => (string)$referenceOneElement['field'],
                    'jsonName'          => (isset($referenceOneElement['json-name'])) ? (string)$referenceOneElement['json-name'] : null,
                ));
            }
        }
        
        // Evaluate <many-to-one ..> mappings
        if (isset($xmlRoot->{"reference-many"})) {
            foreach ($xmlRoot->{"reference-many"} as $referenceManyElement) {
                $class->mapManyToMany(array(
                    'cascade'           => (isset($referenceManyElement->cascade)) ? $this->getCascadeMode($referenceManyElement->cascade) : 0,
                    'targetDocument'    => (string)$referenceManyElement['target-document'],
                    'fieldName'         => (string)$referenceManyElement['field'],
                    'jsonName'          => (isset($referenceManyElement['json-name'])) ? (string)$referenceManyElement['json-name'] : null,
                    'mappedBy'          => (isset($referenceManyElement['mapped-by'])) ? (string)$referenceManyElement['mapped-by'] : null,
                ));
            }
        }
        
        // Evaluate <attachments ..> mapping
        if (isset($xmlRoot->{"attachments"})) {
            $class->mapAttachments((string)$xmlRoot->{"attachments"}[0]['field']);
        }
        
        // Evaluate <embed-one />
        if (isset($xmlRoot->{'embed-one'})) {
            foreach ($xmlRoot->{'embed-one'} AS $embedOneElement) {
                $class->mapEmbedded(array(
                    'targetDocument'    => (string)$embedOneElement['target-document'],
                    'fieldName'         => (string)$embedOneElement['field'],
                    'jsonName'          => (isset($embedOneElement['json-name'])) ? (string)$embedOneElement['json-name'] : null,
                    'embedded'          => 'one',
                ));
            }
        }
        
        // Evaluate <embed-many />
        if (isset($xmlRoot->{'embed-many'})) {
            foreach ($xmlRoot->{'embed-many'} AS $embedManyElement) {
                $class->mapEmbedded(array(
                    'targetDocument'    => (string)$embedManyElement['target-document'],
                    'fieldName'         => (string)$embedManyElement['field'],
                    'jsonName'          => (isset($embedManyElement['json-name'])) ? (string)$embedManyElement['json-name'] : null,
                    'embedded'          => 'many',
                ));
            }
        }
    }

    protected function loadMappingFile($file)
    {
        $result = array();
        $xmlElement = simplexml_load_file($file);

        foreach (array('document', 'embedded-document') as $type) {
            if (isset($xmlElement->$type)) {
                foreach ($xmlElement->$type as $documentElement) {
                    $documentName = (string) $documentElement['name'];
                    $result[$documentName] = $documentElement;
                }
            }
        }

        return $result;
    }
    
    /**
     * Gathers a list of cascade options found in the given cascade element.
     *
     * @param $cascadeElement The cascade element.
     * @return array The list of cascade options.
     */
    private function getCascadeMode($cascadeElement)
    {
        $cascade = 0;
        foreach ($cascadeElement->children() as $action) {
            // According to the JPA specifications, XML uses "cascade-persist"
            // instead of "persist". Here, both variations
            // are supported because both YAML and Annotation use "persist"
            // and we want to make sure that this driver doesn't need to know
            // anything about the supported cascading actions
            $cascadeMode = str_replace('cascade-', '', $action->getName());
            $cascade = constant('Doctrine\ODM\CouchDB\Mapping\ClassMetadata::CASCADE_' . strtoupper($cascadeMode));
        }
        return $cascade;
    }
}
