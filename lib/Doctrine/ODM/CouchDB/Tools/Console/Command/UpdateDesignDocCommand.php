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
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ODM\CouchDB\Tools\Console\Command;

use Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Console\Command\Command;

class UpdateDesignDocCommand extends Command
{
    protected function configure()
    {
        $this->setName('couchdb:odm:update-design-doc')
             ->setDescription('Update all new/modified registered design docs or a single document if a docname is provided.')
             ->setDefinition(array(
                new InputArgument('docname', InputArgument::OPTIONAL, '(Optional) Design doc name as registered in DM configuration, otherwise all new/modified docs are updated.', null),
             ));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dm = $this->getHelper('couchdb')->getDocumentManager();
        $couchDbClient = $dm->getCouchDBClient();
        $config = $dm->getConfiguration();

        //If a docname is provided update only that document,
        //otherwise update all modified/new docs.
        if (is_string($inputDoc = $input->getArgument('docname'))) {
            $designDocNames = array($inputDoc);
        } else {
            $designDocNames = $config->getDesignDocumentNames();
        }

        $foundChanges = false;
        foreach ($designDocNames as $docName) {
            $designDocData = $config->getDesignDocument($docName);

            $localDesignDoc = new $designDocData['className']($designDocData['options']);
            $localDocBody = $localDesignDoc->getData();

            $remoteDocBody = $couchDbClient->findDocument('_design/' . $docName)->body;
            if ($this->isMissingOrDifferent($localDocBody, $remoteDocBody)) {
                $response = $couchDbClient->createDesignDocument($docName, $localDesignDoc);
                $foundChanges = true;

                if ($response->status < 300) {
                    $output->writeln("Succesfully updated: " . $docName);
                } else {
                    $output->writeln("Error updating {$docName}: {$response->body['reason']}");
                }
            }
        }
        if (!$foundChanges) {
            $output->writeln("No changes found; nothing to do.");
        }
    }
    
    private function isMissingOrDifferent($local, $remote) {
        if (is_null($remote) || (isset($remote['error']) && $remote['error'] == 'not_found')) {
            return true;
        }
        foreach ($local as $key => $val) {
            if (!isset($remote[$key]) || $remote[$key] != $val) {
                return true;
            }
            unset($remote[$key]);
        }
        // If any items remain (excluding _id and _rev) the remote is different.
        if (count($remote) > 2) {
            return true;
        }
        return false;
    }
}
