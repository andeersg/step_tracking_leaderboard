<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\GoogleService;
use App\Entity\StepData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Google_Service_Fitness;
use Google_Service_Fitness_AggregateBy;
use Google_Service_Fitness_BucketByTime;
use Google_Service_Fitness_AggregateRequest;


class StepFetcherCommand extends Command
{
    private $userRepository;

    private $googleService;

    protected static $defaultName = 'app:steps:sync';

    public function __construct(UserRepository $userRepository, GoogleService $googleService, EntityManagerInterface $em)
    {
        $this->userRepository = $userRepository;
        $this->googleService = $googleService;
        $this->em = $em;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Fetches step data for users')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run') ? TRUE : FALSE;

        $users = $this->userRepository->findAll();

        if ($dryRun) {
            $io->note('Dry mode enabled');
        }

        $count = 0;

        foreach ($users as $user) {
            $client = $this->googleService->getClient();
            $count += 1;

            // Set token
            $token = $client->fetchAccessTokenWithRefreshToken($user->getRefreshToken());
            $client->setAccessToken($token['access_token']);

            // Fetch data.
            $fitness = new Google_Service_Fitness($client);
            $aggBy = new Google_Service_Fitness_AggregateBy();
            $bucketBy = new Google_Service_Fitness_BucketByTime();
            $aggReq = new Google_Service_Fitness_AggregateRequest();
            $datasets = $fitness->users_dataset;

            $prevDay = $this->googleService->calculateTimestamps();
            $dayBefore = $this->googleService->calculateTimestamps(7);


            $aggBy->setDataTypeName("com.google.step_count.delta");
            $bucketBy->setDurationMillis(86400000); // We want a day.
            $aggReq->startTimeMillis = $dayBefore['start'];
            $aggReq->endTimeMillis = $prevDay['end'];
            $aggReq->setAggregateBy([$aggBy]);
            $aggReq->setBucketByTime($bucketBy);
            $aggregates = $datasets->aggregate('me',$aggReq);


            $result = $this->googleService->processAggregatedData($aggregates);

            // Store in database.
            foreach ($result as $ts => $steps) {
                $stepObject = $this->em->getRepository(StepData::class)->findOneBy([
                    'user_id' => $user->getId(),
                    'date' => new \DateTime(date('o-m-d 00:00:00', $ts / 1000)),
                ]);
                if (!$stepObject) {
                    $stepObject = new StepData();
                    $stepObject->setDate(new \DateTime(date('o-m-d 00:00:00', $ts / 1000)));
                    $stepObject->setUserId($user);
                    $this->em->persist($stepObject);
                }

                $stepObject->setSteps($steps);

                $this->em->flush();
            }
        }

        $io->success(sprintf('Finished with "%d" users', $count));

        return 0;
    }
}
