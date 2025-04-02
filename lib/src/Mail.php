<?php
/* define application namespace */
namespace Nematrack;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use DateTime;
use Monolog\Logger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Class description
 */
final class Mail
{
	/**
	 * The shared logger object
	 *
	 * @var    Logger
	 * @since  2.9.0
	 */
	protected Logger $logger;

	/**
	 * Class construct
	 *
	 * @param   array $options  An array of instantiation options.
	 *
	 * @since   2.9
	 */
	public function __construct(array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		// Get app configuration.
		$config = Factory::getConfig();

		// Get logger object.
		$this->logger = Factory::getLogger([
			'context' => get_class($this),
			'type'    => 'rotate',
			'path'    => FTKPATH_LOGS . DIRECTORY_SEPARATOR . 'mailer' . DIRECTORY_SEPARATOR . 'mail.log',
			'level'   => 'INFO'
		]);
		
		// $this->logger->log('info', 'sendMail', ['key' => 'value']);

		// Configure transport layer.
		$transport = Transport::fromDsn(sprintf('smtp://%s:%s@%s:%d?verify_peer=1',
			$config->get('smtpuser'),
			$config->get('smtppass'),
			$config->get('smtphost'),
			$config->get('smtpport')
		));

		// Configure email.
		$txtEmail = (new Email())
//		->from('noreply@nematrack.com')												// email address as a simple string
//		->from(new Address('noreply@nematrack.com'))								// email address as an object
		->from(new Address('noreply@nematrack.com', 'Nematrack'))				// defining the email address and name as an object (email clients will display the name)
//		->to('development@nematrack.com')
//		->to(new Address('development@nematrack.com'))
		->to(new Address('development@nematrack.com', 'Nematrack Development'))
//		->cc('cc@example.com')
//		->bcc('bcc@example.com')
//		->replyTo('fabien@example.com')
//		->priority(Email::PRIORITY_HIGH)
		->subject('Time for Symfony Mailer!')
		->text('Sending emails is fun again!')
		->html('<p>See Twig integration for better HTML integration!</p>')
		->getHeaders()
		->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');	// tell auto-repliers to not reply to this message because it's an automated email;
		
		$htmlEmail = (new TemplatedEmail())
//		->from('noreply@nematrack.com')												// email address as a simple string
//		->from(new Address('noreply@nematrack.com'))								// email address as an object
		->from(new Address('noreply@nematrack.com', 'Nematrack'))				// defining the email address and name as an object (email clients will display the name)
//		->to('development@nematrack.com')
//		->to(new Address('development@nematrack.com'))
		->to(new Address('development@nematrack.com', 'Nematrack Development'))
//		->cc('cc@example.com')
//		->bcc('bcc@example.com')
//		->replyTo('fabien@example.com')
//		->priority(Email::PRIORITY_HIGH)
		->subject('Thanks for signing up!')
		// path of the Twig template(s) to render
//		->textTemplate('emails/account_created.html.txt.twig')
		->htmlTemplate('emails/account_created.html.twig')
		// pass variables (name => value) to the template
		->context([
			'expiration_date' => new DateTime('+7 days'),
			'username'        => 'foo',
		])
		->getHeaders()
		->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');	// tell auto-repliers to not reply to this message because it's an automated email;

		// Get mailer instance.
		$mailer = new Mailer($transport);

		// Send mail.
		try
		{
			// $txtEmailSent  = $mailer->send($txtEmail);
			// $htmlEmailSent = $mailer->send($htmlEmail);
		}
		catch (TransportExceptionInterface $e)
		{
			// some error prevented the email sending;
			// display an error message or try to resend the message
			die($e->getMessage());
		}
	}
}
