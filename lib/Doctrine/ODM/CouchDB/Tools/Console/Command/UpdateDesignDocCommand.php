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

class UpdateDesignDocCommand extends Command
{
    protected function configure()
    {
        $this->setName('couchdb:odm:update-design-doc')
             ->setDescription('Update design document')
             ->setDefinition(array(
                new InputArgument('docname', InputArgument::REQUIRED, 'Design doc name as registered in DocumentManager configuration.', null),
             ));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dm = $this->getHelper('couchdb')->getDocumentManager();
        $designDocData = $dm->getConfiguration()->getDesignDocument($input->getArgument('docname'));

        $designDoc = new $designDocData['className']($designDocData['options']);

        $response = $dm->getCouchDBClient()->createDesignDocument($input->getArgument('docname'), $designDoc);

        if ($response->status < 300) {
            $output->writeln("Design document was updated succesfully.");
        } else {
            throw new \RuntimeException($response->body['reason']);
        }
    }
}