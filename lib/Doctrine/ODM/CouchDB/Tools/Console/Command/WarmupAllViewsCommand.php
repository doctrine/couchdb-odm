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

use Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputOption;

class WarmupAllViewsCommand extends Command
{
    protected function configure()
    {
        $this->setName('couchdb:odm:warmup-all-views')
            ->setDescription('Trigger all views and start the indexer.')
            ->setDefinition(
                array(
                    new InputOption('update-after', 'a', InputOption::VALUE_NONE, 'Trigger all views at once otherwise sequential', null),
                )
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dm     = $this->getHelper('couchdb')->getDocumentManager();
        $config = $dm->getConfiguration();

        $designDocNames = $config->getDesignDocumentNames();

        foreach ($designDocNames as $docName) {
            $designDocData = $config->getDesignDocument($docName);

            $localDesignDoc = new $designDocData['className']($designDocData['options']);
            $localDocBody   = $localDesignDoc->getData();

            if (array_key_exists('views', $localDocBody)) {
                foreach (array_keys($localDocBody['views']) as $view) {
                    $output->writeln(sprintf('%s/%s', $docName, $view));

                    $query = $dm->createQuery($docName, $view)->setLimit(1);

                    if ($input->getOption('update-after')) {
                        $query->setStale('update_after');
                    }

                    $query->execute();
                }
            }
        }
    }
}
