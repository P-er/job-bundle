<?php
/*
* This file is part of the job-bundle package.
*
* (c) Hannes Schulz <hannes.schulz@aboutcoders.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Abc\Bundle\JobBundle\Model;

use Abc\Bundle\JobBundle\Job\Status;
use Abc\Bundle\JobBundle\Validator\Constraints as AssertJob;
use Abc\Bundle\SchedulerBundle\Model\ScheduleInterface as BaseScheduleInterface;
use Doctrine\Common\Collections\ArrayCollection;
use JMS\Serializer\Annotation as JMS;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Hannes Schulz <hannes.schulz@aboutcoders.com>
 * @AssertJob\Job
 */
class Job implements JobInterface
{
    /**
     * @JMS\Type("string")
     * @Assert\Uuid
     * @var string
     */
    protected $ticket;

    /**
     * @JMS\Type("string")
     * @Assert\NotBlank
     * @AssertJob\JobType
     * @var string
     */
    protected $type;

    /**
     * @JMS\Type("string")
     * @var string
     */
    protected $status;

    /**
     * @JMS\Exclude
     * @var Status
     */
    protected $enumStatus;

    /**
     * @JMS\Type("Abc\Bundle\JobBundle\Job\JobParameterArray");
     * @var array
     */
    protected $parameters;

    /**
     * @JMS\Type("integer")
     * @var integer
     */
    protected $processingTime;

    /**
     * @JMS\Type("DateTime")
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @JMS\Type("DateTime")
     * @var \DateTime|null
     */
    protected $terminatedAt;

    /**
     * @var mixed
     */
    protected $response;

    /**
     * @JMS\Type("ArrayCollection<Abc\Bundle\JobBundle\Model\Schedule>")
     * @var ArrayCollection|Schedule[]
     */
    protected $schedules;

    /**
     * @param string|null $type
     * @param array|null  $parameters
     */
    public function __construct($type = null, $parameters = null)
    {
        $this->schedules  = new ArrayCollection();
        $this->type       = $type;
        $this->parameters = $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function setTicket($ticket)
    {
        $this->ticket = $ticket;
    }

    /**
     * {@inheritdoc}
     */
    public function getTicket()
    {
        return $this->ticket;
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function setParameters($parameters = null)
    {
        $this->parameters = $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function setResponse($response = null)
    {
        return $this->response = $response;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus()
    {
        if(null != $this->status && null == $this->enumStatus) {
            $this->enumStatus = new Status((string)$this->status);
        }

        return $this->enumStatus;
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus(Status $status)
    {
        $this->status = $status->getValue();
        $this->enumStatus = $status;
    }

    /**
     * {@inheritdoc}
     */
    public function hasSchedules()
    {
        return 0 !== $this->schedules->count();
    }

    /**
     * {@inheritdoc}
     */
    public function addSchedule(BaseScheduleInterface $schedule)
    {
        $this->schedules->add($schedule);
    }

    /**
     * {@inheritdoc}
     */
    public function removeSchedule(BaseScheduleInterface $schedule)
    {
        $this->schedules->removeElement($schedule);
    }

    /**
     * {@inheritdoc}
     */
    public function removeSchedules()
    {
        foreach ($this->getSchedules() as $schedule) {
            $this->removeSchedule($schedule);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSchedules()
    {
        return $this->schedules;
    }

    /**
     * {@inheritdoc}
     */
    public function setCreatedAt(\DateTime $createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * {@inheritdoc}
     */
    public function setTerminatedAt(\DateTime $terminatedAt)
    {
        $this->terminatedAt = $terminatedAt;
    }

    /**
     * {@inheritdoc}
     */
    public function getTerminatedAt()
    {
        return $this->terminatedAt;
    }

    /**
     * {@inheritdoc}
     */
    public function setProcessingTime($milliseconds)
    {
        $this->processingTime = $milliseconds;
    }

    /**
     * {@inheritdoc}
     */
    public function getProcessingTime()
    {
        return $this->processingTime;
    }

    /**
     * {@inheritdoc}
     */
    public function getExecutionTime()
    {
        $terminationTimestamp = $this->terminatedAt == null ? time() : $this->terminatedAt->format('U');

        return $terminationTimestamp - $this->createdAt->format('U');
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->getTicket();
    }

    /**
     * Override clone in order to avoid duplicating entries in Doctrine
     */
    public function __clone()
    {
        $this->ticket = null;
    }

    /**
     * Ensures that the member variable $schedules is an ArrayCollection after deserialization
     *
     * @JMS\PostDeserialize
     */
    private function postSerialize()
    {
        if ($this->schedules == null) {
            $this->schedules = new ArrayCollection();
        }
        if($this->status != null && $this->enumStatus == null) {
            $this->getStatus();
        }
    }
}