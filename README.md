AbcJobBundle
============

A symfony bundle that allows you to process jobs asynchronously, by simply annotating the method of a class and registering it within the service container.

Build Status: [![Build Status](https://travis-ci.org/aboutcoders/job-bundle.svg?branch=master)](https://travis-ci.org/aboutcoders/job-bundle)

## Overview

This bundle provides the following features:

- Asynchronous job processing
- Scheduled execution of jobs
- RESTful API
- Message Queue Backend based on RabbitMQ or Doctrine

## Disclaimer

Please note that this bundle is still in development and thus we feel free to change things including the external API if necessary. We are planning to release the first stable release the next weeks.

We appreciate if you decide to use this bundle and we appreciate your feedback, suggestions or contributions.

## Installation

Follow the installation instructions of the required third party bundles:

* [SensioFrameworkExtraBundle](http://symfony.com/doc/current/bundles/SensioFrameworkExtraBundle)
* [FOSRestBundle](https://github.com/FriendsOfSymfony/FOSRestBundle)
* [NelmioApiDocBundle](https://github.com/nelmio/NelmioApiDocBundle)
* [JMSSerializerBundle](https://github.com/schmittjoh/JMSSerializerBundle)
* [AbcSchedulerBundle](https://github.com/aboutcoders/scheduler-bundle)
* [AbcProcessControlBundle](https://github.com/aboutcoders/process-control-bundle)
* [AbcResourceLockBundle](https://github.com/aboutcoders/resource-lock-bundle)
* [AbcEnumSerializerBundle](https://github.com/aboutcoders/enum-serializer-bundle)
* [SonataNotificationBundle](https://github.com/sonata-project/SonataNotificationBundle)
* [YZSupervisorBundle](https://github.com/yzalis/SupervisorBundle)

Add the AbcJobBundle to your `composer.json` file

```json
{
    "require": {
        "aboutcoders/job-bundle": "dev-master"
    }
}
```

Include the bundle in the AppKernel.php class

```php
public function registerBundles()
{
    $bundles = array(
        // ...
        new Abc\Bundle\JobBundle\AbcJobBundle(),
    );

    return $bundles;
}
```

## Configuration

__Configure doctrine orm__

At the current point only doctrine is supported as ORM. However by changing the configuration you can use a different persistence layer.

```yaml
abc_job:
  db_driver: orm
```

__Register a doctrine mapping type for the job status__

```yaml
doctrine:
    dbal:
        types:
            abc.job.status: Abc\Bundle\JobBundle\Doctrine\Types\StatusType
```

__Register GDMO Timestampable__

The bundle makes use of the GDMO Timestampable behavior. There are different approaches on how you can set up this behavior. Please refer to the [official symfony documentation](http://symfony.com/doc/current/cookbook/doctrine/common_extensions.html) and follow the instructions there.

__Import AbcJobBundle routing files__

If you want to work with the REST-API you have to import the routing files.

```yaml
abc-rest-job:
    type: rest
    resource: "@AbcJobBundle/Resources/config/routing/rest-job.yml"
    prefix: /api
```

__Update the database schema__

Finally you need to update your database schema in order to create the required tables.

```bash
php app/console doctrine:schema:update --force
```

__Check swiftmailer configuration__

If you are using the mailer job that is shipped with this bundle you have to make sure that the spool configuration is changed so that mails are sent at once and not only if the kernel terminates, which is the default behavior (see: [How to Spool Emails](http://symfony.com/doc/current/email/spool.html)).

To change this configuration only for the job processing and leave the default configuration as is you have to run the job processing in a dedicated environment (see: [How to Master and Create new Environments](http://symfony.com/doc/current/configuration/environments.html))).

## Basic Usage

### Adding a custom job

To register a new job, you have to do two things

- Create the job class
- Register the job class in the service container

#### Step 1: Create the job class

First you have to create the class that will perform the actual work. This can be any kind of class.

```php
namespace My\Bundle\ExampleBundle\Job\MyJob;

use Abc\Bundle\JobBundle\Annotation\JobParameters;
use Abc\Bundle\JobBundle\Annotation\JobResponse;

class MyJob
{
    /**
     * @JobParameters({"string", "@logger"})
     * @JobResponse("string")
     */
    public function sayHello($whom, Logger $logger)
    {
        $logger->debug('Hello ' . $whom);

        return 'Hello ' . $whom;
    }
}
```

Please note the two annotations __@JobParameters__ and __@JobResponse__. They are used to specify the type of parameters the job must be invoked with as well as the type of response that is returned by the job. Since jobs are executed in the background both parameters and response must be serializable in order to persist them. However there is special parameter type called [runtime parameter](./docs/howto-inject-runtime-parameters.md), that are specified with the `@` character. Runtime parameters can be of type, since they are provided at runtime by event listeners. The `@logger` in the previous example is such a runtime parameter. The `@logger` is a runtime parameters that is available for every job. It provides a dedicated PSR compliant logger for each job.

Both parameters and response (return value of a job) are serialized/deserialized using the [JMS Serializer](http://jmsyst.com/libs/serializer).

__Note:__ You only have to provide the __@JobParameters__ or __@JobResponse__ in case your job requires parameters or returns a response.


#### Step 2: Register the class in the service container

Next you have to register the job as a service within the service container and tag it.

```yaml
# app/config/services.yml

services:
    my_job:
        class: My\Bundle\ExampleBundle\Job\MyJob
        tags:
            -  { name: "abc.job", type: "say_hello", method: "sayHello" }
```

The tag with name `abc.job` must define the two attributes `type` and `method` where `type` defines the unique name of the job (e.g. "mailer") and `method` references the method of the job class to must be executed.

### Adding a job for asynchronous processing

To execute a job asynchronously you need to retrieve the job manager from the service container and add the job.

```php
$manager = $container->get('abc.job.manager')

$job = $manager->addJob('say_hello', array('World'));
```

The first argument of `addJob` specifies the type (unique name) of the job. This value must equal `type` that was chosen in the service tag. The second (optional) argument is an array of parameters the job will be executed with.

The return value is an implementation of [JobInterface](./Job/JobInterface.php). The most important attribute here is the job ticket. You can retrieve it like follows:

```php
$ticket = $job->getTicket();
```

The job ticket can be used to retrieve information about the job at a later point. Besides that you have access to more detailed information about the job such as status, execution time, associated schedules and so on. Please refer to the documentation of the [JobManagerInterface](../Job/JobManagerInterface.php) to get an overview of the full API.

### Scheduling a job

You can define schedules for jobs in order to execute them repeatedly. To do so you simply have to provide the schedule as an argument when the job is added to the manager.

```php
/**
 * @var Abc\Bundle\JobBundle\Job\ManagerInterface
 */
$manager = $container->get('abc.job.manager');

$job = $manager->create('say_hello', array('World'));

$schedule = $job->createSchedule('cron', '*/5 * * * *');

$job = $manager->add($job);
```

This will create a job that is executed every 5 minutes. Please take a look at the documentation o the [AbcSchedulerBundle](https://github.com/aboutcoders/scheduler-bundle) to get more information on how to work with schedules.

## How-Tos
- [How-to work with the manager](./Resources/docs/howto-manager.md)
- [How-to make a job cancellable at runtime](./Resources/docs/howto-make-a-job-cancellable-at-runtime.md)
- [How-to inject runtime parameters](./Resources/docs/howto-inject-runtime-parameters.md)
- [How-to modify a job at runtime](./Resources/docs/howto-modify-job.md)
- [How-to manage jobs at runtime](./Resources/docs/howto-manage-jobs-at-runtime.md)
- [How-to work with the job status](./Resources/docs/howto-status.md)

## Further Documentation

- [Scheduled jobs](./Resources/docs/scheduled-jobs.md)
- [Lifecycle events](./Resources/docs/lifecycle-events.md)
- [Logging](./Resources/docs/logging.md)
- [The REST-API](./Resources/docs/rest.md)
- [Configuration Reference](./Resources/docs/configuration-reference.md)

## ToDo:
- Unit test both sonata and bernard integration tests
- Make QueueConfig configurable & document
- Add unit tests for bernard and sonata adapters
- Remove all logging channels except abc.job
- Update documentation
- Add consumer command
- Test setting queue for job types in registry

### Stable release:
- Get rid of forms and use validation service inside manager to validate job parameters
- Document how to manage queues within soanta (abc.job prefix)
- Define & document a clear serializer interface
- Provider a TestCase for jobs
- Cleanup & document agent management
- Cleanup & document queue management
- Cleanup & document supervisor integration
- Cleanup/document Eraser

### Planned Features:
- Support XML as API response format
- Utilize stopwatch to detect bottle necks
- Statistics
- Monitoring
- Support alternative queue backends such as [qpush-bundle](https://www.google.de/webhp?q=qpushbundle) or [IronMQ](https://www.iron.io/platform/ironmq/)