<?php
/**
 * Created by PhpStorm.
 * User: thewbb
 * Date: 19-1-29
 * Time: 下午9:09
 */

namespace thewbb\thinwork\console;

use Di;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;


/**
 * @property Di $di
 */
class ThinworkAdminCreate extends Command {

    private $di;

    function __construct($di) {
        $this->di = $di;
        parent::__construct("thinwork:admin:create");
    }


    protected function configure()
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Creates a new admin controller.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command allows you to create a admin controller...')
            //->addArgument('username', InputArgument::REQUIRED, 'The username of the user.')
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //$output->writeln('Username: '.$input->getArgument('username'));

        $helper = $this->getHelper('question');
//        $question = new ConfirmationQuestion('Continue with this action?(Y/N)', false);
//
//        if ($helper->ask($input, $output, $question)) {
//            echo "yes";
//        }
//        else{
//            echo "no";
//        }

        $bundles = ['AcmeDemoBundle', 'AcmeBlogBundle', 'AcmeStoreBundle'];
        $question = new Question('Please enter the name of a controller');
        $question->setAutocompleterValues($bundles);

        $bundleName = $helper->ask($input, $output, $question);
        echo $bundleName;
    }
} 