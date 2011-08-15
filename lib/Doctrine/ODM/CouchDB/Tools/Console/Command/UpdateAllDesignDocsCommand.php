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

namespace Doctrine\ODM\CouchDB\Tools\Console\Command;

use Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Console\Command\Command;

class UpdateAllDesignDocsCommand extends Command
{
    protected function configure()
    {
        $this->setName('couchdb:odm:update-all-design-docs')
             ->setDescription('Update all new/modified design documents registered in the DM configuration.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dm = $this->getHelper('couchdb')->getDocumentManager();
        $couchDbClient = $dm->getCouchDBClient();
        $config = $dm->getConfiguration();
        $designDocNames = $config->getDesignDocumentNames();

        foreach ($designDocNames as $docName) {
            $designDocData = $config->getDesignDocument($docName);

            $localDesignDoc = new $designDocData['className']($designDocData['options']);
            $localDocBody = $localDesignDoc->getData();

            $remoteDocBody = $couchDbClient->findDocument('_design/' . $docName)->body;

            if (is_null($remoteDocBody) || ($remoteDocBody['views'] != $localDocBody['views'])) {
                $response = $dm->getCouchDBClient()->createDesignDocument($docName, $localDesignDoc);

                if ($response->status < 300) {
                    $output->writeln("Succesfully updated: " . $docName);
                } else {
                    $output->writeln("Error updating {$docName}: {$response->body['reason']}");
                }
            }
        }
    }
}