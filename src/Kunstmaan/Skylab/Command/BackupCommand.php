<?php
namespace Kunstmaan\Skylab\Command;

use Kunstmaan\Skylab\Skeleton\AbstractSkeleton;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * BackupCommand
 */
class BackupCommand extends AbstractCommand
{

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this
            ->addDefaults()
            ->setName('backup')
            ->setDescription('Run backup on all or one Skylab projects')
            ->addArgument('project', InputArgument::OPTIONAL, 'If set, the task will only backup the project named')
            ->addOption("--quick", null, InputOption::VALUE_NONE, 'If set, no tar.gz file will be created, only the preBackup and postBackup hooks will be executed.')
            ->addOption("--anonymize", null, InputOption::VALUE_NONE, 'If set, the database backup will be anonymized')
            ->setHelp(<<<EOT
The <info>backup</info> command will dump all your databases and create a tarball of one or all projects.

<info>php skylab.phar backup</info>                         # Will backup all projects
<info>php skylab.phar backup myproject</info>               # Will backup the myproject project
<info>php skylab.phar backup myproject --quick</info>       # Will backup the myproject project, but not create the tar file.

EOT
            );
    }

    protected function doExecute()
    {
        $onlyprojectname = $this->input->getArgument('project');
        $this->fileSystemProvider->projectsLoop(function ($project) use ($onlyprojectname) {
            if (isset($onlyprojectname) && $project["name"] != $onlyprojectname) {
                return;
            }
            $project['anonymize'] = $this->input->getOption('anonymize');
            $this->dialogProvider->logStep("Running backup on project " . $project["name"]);
            $this->skeletonProvider->skeletonLoop(function (AbstractSkeleton $theSkeleton) use ($project) {
                $this->dialogProvider->logTask("Running preBackup for skeleton " . $theSkeleton->getName());
                $theSkeleton->preBackup($project);
            }, new \ArrayObject($project["skeletons"]));
            if (!$this->input->getOption('quick')) {
                $this->dialogProvider->logTask("Tarring the project folder");
                $this->fileSystemProvider->runTar($project);
            }
            $this->skeletonProvider->skeletonLoop(function (AbstractSkeleton $theSkeleton) use ($project) {
                $this->dialogProvider->logTask("Running postBackup for skeleton " . $theSkeleton->getName());
                $theSkeleton->postBackup($project);
            }, new \ArrayObject($project["skeletons"]));
        });
    }
}
