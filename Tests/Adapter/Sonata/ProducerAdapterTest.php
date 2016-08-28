<?php
/*
* This file is part of the job-bundle package.
*
* (c) Hannes Schulz <hannes.schulz@aboutcoders.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Abc\Bundle\JobBundle\Tests\Sonata;

use Abc\Bundle\JobBundle\Adapter\Sonata\ProducerAdapter;
use Abc\Bundle\JobBundle\Job\JobType;
use Abc\Bundle\JobBundle\Job\JobTypeRegistry;
use Abc\Bundle\JobBundle\Job\ManagerInterface;
use Abc\Bundle\JobBundle\Job\Queue\Message;
use Psr\Log\LoggerInterface;
use Sonata\NotificationBundle\Backend\BackendInterface;
use Sonata\NotificationBundle\Backend\QueueBackendDispatcher;
use Sonata\NotificationBundle\Consumer\ConsumerEvent;
use Sonata\NotificationBundle\Model\MessageInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @author Hannes Schulz <hannes.schulz@aboutcoders.com>
 */
class ProducerAdapterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var BackendInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $backend;

    /**
     * @var EventDispatcherInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $eventDispatcher;

    /**
     * @var JobTypeRegistry|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $registry;

    /**
     * @var ManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $manager;

    /**
     * @var LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $logger;

    /**
     * @var ProducerAdapter
     */
    private $subject;

    public function setUp()
    {
        $this->backend         = $this->getMockBuilder(QueueBackendDispatcher::class)->disableOriginalConstructor()->getMock();
        $this->manager         = $this->getMock(ManagerInterface::class);
        $this->registry        = $this->getMockBuilder(JobTypeRegistry::class)->disableOriginalConstructor()->getMock();
        $this->eventDispatcher = $this->getMock(EventDispatcherInterface::class);
        $this->logger          = $this->getMock(LoggerInterface::class);

        $this->registry->expects($this->any())
            ->method('getDefaultQueue')
            ->willReturn('default');

        $this->subject = new ProducerAdapter($this->backend, $this->eventDispatcher, $this->registry, $this->logger);
        $this->subject->setManager($this->manager);
    }

    public function testSetManager()
    {
        $this->subject->setManager($this->manager);

        $this->assertAttributeSame($this->manager, 'manager', $this->subject);
    }

    public function testProduceWithDefaultQueue()
    {
        $message = new Message('type', 'ticket', 'callback');

        $jobType = $this->getMockBuilder(JobType::class)->disableOriginalConstructor()->getMock();
        $jobType->expects($this->any())
            ->method('getQueue')
            ->willReturn('default');

        $this->registry->expects($this->any())
            ->method('get')
            ->with('type')
            ->willReturn($jobType);

        $this->backend->expects($this->once())
            ->method('createAndPublish')
            ->with(ProducerAdapter::MESSAGE_PREFIX . 'type', array('ticket' => 'ticket'));

        $this->subject->produce($message);
    }

    public function testProduceWithDifferentQueue()
    {
        $message = new Message('type', 'ticket', 'callback');
        $backend = $this->getMock(BackendInterface::class);

        $jobType = $this->getMockBuilder(JobType::class)->disableOriginalConstructor()->getMock();
        $jobType->expects($this->any())
            ->method('getQueue')
            ->willReturn('other_queue');

        $this->backend->expects($this->once())
            ->method('getBackend')
            ->with('other_queue')
            ->willReturn($backend);

        $this->registry->expects($this->any())
            ->method('get')
            ->with('type')
            ->willReturn($jobType);

        $backend->expects($this->once())
            ->method('createAndPublish')
            ->with(ProducerAdapter::MESSAGE_PREFIX . 'type', array('ticket' => 'ticket'));

        $this->subject->produce($message);
    }

    /**
     * @expectedException \Exception
     */
    public function testProduceThrowsExceptionsThrownByBackend()
    {
        $message = new Message('type', 'ticket', 'callback');

        $jobType = $this->getMockBuilder(JobType::class)->disableOriginalConstructor()->getMock();
        $jobType->expects($this->any())
            ->method('getQueue')
            ->willReturn('default');

        $this->registry->expects($this->any())
            ->method('get')
            ->with('type')
            ->willReturn($jobType);

        $this->backend->expects($this->once())
            ->method('createAndPublish')
            ->willThrowException(new \Exception);

        $this->subject->produce($message);
    }

    /**
     * @param string $type
     * @param string $ticket
     * @dataProvider getEventData
     */
    public function testProcess($type, $ticket)
    {
        $body = array(
            'ticket' => $ticket
        );

        $event = $this->createConsumerEvent($type, $body);

        $expectedMessage = new Message($type, $ticket);

        $this->manager->expects($this->once())
            ->method('onMessage')
            ->with($expectedMessage);

        $this->subject->process($event);
    }

    /**
     * @param ConsumerEvent $event
     * @dataProvider getInvalidEvent
     * @expectedException \InvalidArgumentException
     */
    public function testProcessThrowsInvalidArgumentException(ConsumerEvent $event)
    {
        $this->manager->expects($this->never())
            ->method('onMessage');

        $this->subject->process($event);
    }

    public static function getEventData()
    {
        return array(
            array('typeA', 'ticket_1'),
            array('typeB', 'ticket_2')
        );
    }

    public function getInvalidEvent()
    {
        return array(
            array($this->createConsumerEvent('foobar', array())),
            array($this->createConsumerEvent('foobar', array('foobar')))
        );
    }

    private function createConsumerEvent($type, $messageBody)
    {
        $message = $this->getMock(MessageInterface::class);

        $message->expects($this->any())
            ->method('getBody')
            ->willReturn($messageBody);

        $message->expects($this->any())
            ->method('getValue')
            ->willReturnCallback(
                function ($key, $default) use ($messageBody) {
                    if (is_array($messageBody) && isset($messageBody[$key])) {
                        return $messageBody[$key];
                    }

                    return $default;
                }
            );

        $message->expects($this->any())
            ->method('getType')
            ->willReturn($type);

        return new ConsumerEvent($message);
    }
}