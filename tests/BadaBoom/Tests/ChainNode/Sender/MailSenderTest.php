<?php

namespace BadaBoom\Tests\ChainNode\Sender;

use BadaBoom\ChainNode\Sender\MailSender;
use BadaBoom\DataHolder\DataHolder;

use Symfony\Component\Serializer\Serializer;

class MailSenderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * 
     * @test
     */
    public function shouldBeSubClassOfAbstractSender()
    {
        $rc = new \ReflectionClass('BadaBoom\ChainNode\Sender\MailSender');
        $this->assertTrue($rc->isSubclassOf('BadaBoom\ChainNode\Sender\AbstractSender'));
    }

    /**
     *
     * @test
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Given sender
     *
     * @dataProvider provideInvalidMail
     */
    public function throwWhenConstructWithIncorrectSender($invalidSender)
    {
        $configuration = new DataHolder();
        $configuration->set('sender', $invalidSender);

        new MailSender($this->createMockAdapter(), $this->createMockSerializer(), $configuration);
    }

    /**
     *
     * @test
     *
     * @depends throwWhenConstructWithIncorrectSender
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Recipients list should not be empty
     */
    public function throwWhenConstructWithEmptyRecipientsList()
    {
        $configuration = new DataHolder();
        $configuration->set('sender', 'valid@sender.com');
        $configuration->set('recipients', array());

        new MailSender($this->createMockAdapter(), $this->createMockSerializer(), $configuration);
    }

    /**
     *
     * @test
     *
     * @depends throwWhenConstructWithEmptyRecipientsList
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Given recipient
     *
     * @dataProvider provideInvalidMail
     */
    public function throwWhenConstructWithIncorrectRecipientsList($invalidRecipient)
    {
        $configuration = new DataHolder();
        $configuration->set('sender', 'valid@sender.com');
        $configuration->set('recipients', array($invalidRecipient));

        new MailSender($this->createMockAdapter(), $this->createMockSerializer(), $configuration);
    }

    /**
     *
     * @test
     * 
     * @depends throwWhenConstructWithIncorrectRecipientsList
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Given recipient
     *
     * @dataProvider provideInvalidMail
     */
    public function throwWhenConstructWithAnyNumberOfIncorrectRecipients($invalidRecipient)
    {
        $configuration = new DataHolder();
        $configuration->set('sender', 'valid@sender.com');
        $configuration->set('recipients', array('valid@recipient.com', $invalidRecipient, 'another_valid@recipient.com'));

        new MailSender($this->createMockAdapter(), $this->createMockSerializer(), $configuration);
    }

    /**
     * @test
     */
    public function shouldValidateSenderAndRecipientsAndFormatInConstruct()
    {
        $sender = 'valid@recipient.com';
        $recipient = array('john@doe.com');
        $format = 'html';

        $serializer = $this->createMockSerializer();
        $serializer->expects($this->once())
            ->method('supportsEncoding')
            ->with($format)
            ->will($this->returnValue(true))
        ;

        $configuration = new DataHolder();
        $configuration->set('format', $format);
        $configuration->set('sender', $sender);
        $configuration->set('recipients', $recipient);

        new MailSender($this->createMockAdapter(), $serializer, $configuration);
    }

    /**
     * @test
     */
    public function shouldSendSerializedContentAccordingGivenConfiguration()
    {
        $data = new DataHolder();
        $serializedData = 'Plain text';
        $configuration = $this->getFullConfiguration();

        $encoder = $this->getMock('Symfony\Component\Serializer\Encoder\EncoderInterface');
        $encoder->expects($this->once())
            ->method('supportsEncoding')
            ->with($configuration->get('format'))
            ->will($this->returnValue(true))
        ;
        $encoder->expects($this->once())
            ->method('encode')
            ->with(array(), $configuration->get('format'))
            ->will($this->returnValue($serializedData))
        ;
        $serializer = new Serializer(array(), array($configuration->get('format') => $encoder));

        $adapter = $this->createMockAdapter();
        $adapter->expects($this->once())
            ->method('send')
            ->with(
                $configuration->get('sender'),
                $configuration->get('recipients'),
                $configuration->get('subject'),
                $serializedData,
                $configuration->get('headers')
            )
        ;
        $sender = new MailSender($adapter, $serializer, $configuration);

        $sender->handle(new \Exception, $data);
    }

    /**
     * @test
     */
    public function shouldTakeSubjectFromHandledDataInsteadOfConfigurationValue()
    {
        $exception = new \Exception;

        $data = new DataHolder();
        $data->set('subject', 'Hey! There is an error.');

        $configuration = new DataHolder();
        $configuration->set('format', 'html');
        $configuration->set('sender', 'valid@sender.com');
        $configuration->set('recipients', array('john@doe.com'));
        $configuration->set('subject', 'Static subject from config');

        $encoder = $this->getMock('Symfony\Component\Serializer\Encoder\EncoderInterface');
        $encoder->expects($this->any())->method('supportsEncoding')->will($this->returnValue(true));
        $encoder->expects($this->any())->method('encode');
        $serializer = new Serializer(array(), array($configuration->get('format') => $encoder));

        $adapter = $this->createMockAdapter();
        $adapter->expects($this->once())
            ->method('send')
            ->with(
                $configuration->get('sender'),
                $configuration->get('recipients'),
                $data->get('subject'),
                null,
                array()
            )
        ;

        $sender = new MailSender($adapter, $serializer, $configuration);
        $sender->handle($exception, $data);
    }

    /**
     * @test
     */
    public function shouldSendEmptySubjectIfItWasNotSetBefore()
    {
        $emptySubject = null;
        $exception = new \Exception;
        $data = new DataHolder();

        $configuration = new DataHolder();
        $configuration->set('format', 'html');
        $configuration->set('sender', 'valid@sender.com');
        $configuration->set('recipients', array('john@doe.com'));
        $configuration->set('headers', array('BB' => 'support@site.com'));

        $encoder = $this->getMock('Symfony\Component\Serializer\Encoder\EncoderInterface');
        $encoder->expects($this->any())->method('supportsEncoding')->will($this->returnValue(true));
        $encoder->expects($this->any())->method('encode');
        $serializer = new Serializer(array(), array($configuration->get('format') => $encoder));

        $adapter = $this->createMockAdapter();
        $adapter->expects($this->once())
            ->method('send')
            ->with(
                $configuration->get('sender'),
                $configuration->get('recipients'),
                $emptySubject,
                null,
                $configuration->get('headers')
            )
        ;

        $sender = new MailSender($adapter, $serializer, $configuration);
        $sender->handle($exception, $data);
    }

    /**
     * @test
     */
    public function shouldSendEmptyHeadersListIfItWasNotSetBefore()
    {
        $emptyHeaderList = array();
        $exception = new \Exception;
        $data = new DataHolder();

        $configuration = new DataHolder();
        $configuration->set('format', 'html');
        $configuration->set('sender', 'valid@sender.com');
        $configuration->set('recipients', array('john@doe.com'));

        $encoder = $this->getMock('Symfony\Component\Serializer\Encoder\EncoderInterface');
        $encoder->expects($this->any())->method('supportsEncoding')->will($this->returnValue(true));
        $encoder->expects($this->any())->method('encode');
        $serializer = new Serializer(array(), array($configuration->get('format') => $encoder));

        $adapter = $this->createMockAdapter();
        $adapter->expects($this->once())
            ->method('send')
            ->with(
                $configuration->get('sender'),
                $configuration->get('recipients'),
                null,
                null,
                $emptyHeaderList
            )
        ;

        $sender = new MailSender($adapter, $serializer, $configuration);
        $sender->handle($exception, $data);
    }

    /**
     * @test
     */
    public function shouldSendMailAndDelegateHandlingToNextChainNode()
    {
        $exception = new \Exception;
        $data = new DataHolder();
        $configuration = $this->getFullConfiguration();

        $encoder = $this->getMock('Symfony\Component\Serializer\Encoder\EncoderInterface');
        $encoder->expects($this->any())->method('supportsEncoding')->will($this->returnValue(true));
        $encoder->expects($this->any())->method('encode');
        $serializer = new Serializer(array(), array($configuration->get('format') => $encoder));

        $adapter = $this->createMockAdapter();
        $adapter->expects($this->any())->method('send');

        $nextChainNode = $this->createMockChainNode();
        $nextChainNode->expects($this->once())->method('handle')->with($this->equalTo($exception), $this->equalTo($data));

        $sender = new MailSender($adapter, $serializer, $configuration);
        $sender->nextNode($nextChainNode);
        
        $sender->handle($exception, $data);
    }

    /**
     * @return \BadaBoom\DataHolder\DataHolder
     */
    protected function getFullConfiguration()
    {
        $configuration = new DataHolder();
        $configuration->set('format', 'html');
        $configuration->set('sender', 'valid@sender.com');
        $configuration->set('subject', 'It should be provided via SubjectProvider');
        $configuration->set('recipients', array('john@doe.com'));
        $configuration->set('headers', array('BB' => 'support@site.com'));

        return $configuration;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function createMockAdapter()
    {
        return $this->getMock('BadaBoom\Adapter\Mailer\MailerAdapterInterface');
    }

    /**
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function createMockSerializer()
    {
        return $this->getMock('Symfony\Component\Serializer\Serializer');
    }

    /**
     * @static
     *
     * @return array
     */
    public static function provideInvalidMail()
    {
        return array(
            array(' '),
            array(''),
            array('john@'),
            array('name@domain.r'),
            array('name@.ru'),
            array('@domain.com'),
            array(1),
            array(1.2),
            array(false),
            array(true),
            array(null),
            array(new \stdClass),
            array(array()),
        );
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function createMockChainNode()
    {
        return $this->getMockForAbstractClass('BadaBoom\ChainNode\AbstractChainNode');
    }
}