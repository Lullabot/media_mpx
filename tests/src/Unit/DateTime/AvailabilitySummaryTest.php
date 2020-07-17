<?php

namespace Drupal\Tests\media_mpx\Unit\DateTime;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\media_mpx\DateTime\AvailabilitySummary;
use Drupal\Tests\UnitTestCase;
use Lullabot\Mpx\DataService\DateTime\ConcreteDateTime;
use Lullabot\Mpx\DataService\DateTime\NullDateTime;
use Lullabot\Mpx\DataService\Media\Media;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests availability summaries.
 *
 * @group media_mpx
 *
 * @coversDefaultClass \Drupal\media_mpx\DateTime\AvailabilitySummary
 */
class AvailabilitySummaryTest extends UnitTestCase {

  /**
   * Short date format.
   */
  const SHORT_DATE_FORMAT = 'm/d/Y - H:i';

  /**
   * The current time.
   *
   * @var int
   */
  protected $currentTime;

  /**
   * Mock date time service.
   *
   * @var \PHPUnit\Framework\MockObject\Builder\InvocationMocker|\Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Mock date format class.
   *
   * Simplified version of Drupal's \Drupal\Core\Datetime\DateFormatter::format
   * that ignore most features for the purpose of testing.
   *
   * @var \PHPUnit\Framework\MockObject\Builder\InvocationMocker|\Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Availability summary object under test.
   *
   * @var \Drupal\media_mpx\DateTime\AvailabilitySummary
   */
  protected $availabilitySummary;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->currentTime = time();
    $this->time = $this->createMock(TimeInterface::class);
    $this->time->expects($this->any())
      ->method('getCurrentTime')
      ->willReturn($this->currentTime);
    $this->dateFormatter = $this->createMock(DateFormatterInterface::class);
    $this->dateFormatter->expects($this->any())
      ->method('format')
      ->willReturnCallback(function ($timestamp, $type = 'medium', $format = '', $timezone = NULL, $langcode = NULL) {
        $format = $format ?: self::SHORT_DATE_FORMAT;
        return date($format, $timestamp);
      });
    $this->availabilitySummary = new AvailabilitySummary($this->time, $this->dateFormatter);

    // Setup a mock string translation service for testing.
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Test for correct output of the getAvailabilitySummary method.
   *
   * @param \Lullabot\Mpx\DataService\Media\Media $mpx_object
   *   Mpx media object.
   * @param bool $include_date
   *   Indicate whether to include the date.
   * @param string $expected_result
   *   The expected summary string.
   *
   * @covers ::getAvailabilitySummary
   *
   * @dataProvider getAvailabilitySummaryProvider
   */
  public function testAvailabilitySummary(Media $mpx_object, bool $include_date, string $expected_result) {
    $summary = $this->availabilitySummary->getAvailabilitySummary($mpx_object, $include_date);
    $this->assertEquals($expected_result, $summary);
  }

  /**
   * Test for correct output of the getAvailableSummary method.
   *
   * @param mixed $expired_date
   *   Expired date.
   * @param bool $include_date
   *   Indicate whether to include the date.
   * @param string $expected_result
   *   The expected summary.
   *
   * @covers ::getAvailableSummary
   *
   * @dataProvider getAvailableSummaryProvider
   */
  public function testGetAvailableSummary($expired_date, bool $include_date, string $expected_result) {
    $summary = $this->availabilitySummary->getAvailableSummary($expired_date, $include_date);
    $this->assertEquals($expected_result, $summary);
  }

  /**
   * Test for correct output of the getUpcomingSummary method.
   *
   * @param mixed $available_date
   *   Available date.
   * @param bool $include_date
   *   Indicate whether to include the date.
   * @param string $expected_result
   *   The expected summary.
   *
   * @covers ::getUpcomingSummary
   *
   * @dataProvider getUpcomingSummaryProvider
   */
  public function testGetUpcomingSummary($available_date, bool $include_date, string $expected_result) {
    $summary = $this->availabilitySummary->getUpcomingSummary($available_date, $include_date);
    $this->assertEquals($expected_result, $summary);
  }

  /**
   * Test for correct output of the getExpiredSummary method.
   *
   * @param mixed $expired_date
   *   Expired date.
   * @param bool $include_date
   *   Indicate whether to include the date.
   * @param string $expected_result
   *   The expected summary.
   *
   * @covers ::getExpiredSummary
   *
   * @dataProvider getExpiredSummaryProvider
   */
  public function testGetExpiredSummary($expired_date, bool $include_date, string $expected_result) {
    $summary = $this->availabilitySummary->getExpiredSummary($expired_date, $include_date);
    $this->assertEquals($expected_result, $summary);
  }

  /**
   * Data provider for testGetAvailabilitySummaryProvider.
   */
  public function getAvailabilitySummaryProvider() {
    $future_date = new \DateTime('+1 week');
    $far_future_date = new \DateTime('+1 year');
    $past_date = new \DateTime('-1 week');
    $distant_past_date = new \DateTime('-1 year');

    $upcoming_media = new Media();
    $upcoming_media->setAvailableDate(new ConcreteDateTime($future_date));
    $upcoming_media->setExpirationDate(new ConcreteDateTime($far_future_date));

    $available_media = new Media();
    $available_media->setAvailableDate(new ConcreteDateTime($past_date));
    $available_media->setExpirationDate(new ConcreteDateTime($future_date));

    $expired_media = new Media();
    $expired_media->setAvailableDate(new ConcreteDateTime($distant_past_date));
    $expired_media->setExpirationDate(new ConcreteDateTime($past_date));

    $empty_media = new Media();

    $available_no_expiration = new Media();
    $available_no_expiration->setAvailableDate(new ConcreteDateTime($past_date));

    return [
      [
        $upcoming_media,
        TRUE,
        sprintf('Upcoming %s', $future_date->format(self::SHORT_DATE_FORMAT)),
      ],
      [$upcoming_media, FALSE, 'Upcoming'],
      [
        $available_media,
        TRUE,
        sprintf('Available until %s', $future_date->format(self::SHORT_DATE_FORMAT)),
      ],
      [$available_media, FALSE, 'Available'],
      [
        $expired_media,
        TRUE,
        sprintf('Expired on %s', $past_date->format(self::SHORT_DATE_FORMAT)),
      ],
      [$expired_media, FALSE, 'Expired'],
      [$empty_media, TRUE, 'Available'],
      [$empty_media, FALSE, 'Available'],
      [$available_no_expiration, TRUE, 'Available'],
      [$available_no_expiration, FALSE, 'Available'],
    ];
  }

  /**
   * Data provider for testGetAvailableSummary.
   */
  public function getAvailableSummaryProvider() {
    $future_date = new \DateTime('+1 week');

    return [
      [NULL, TRUE, 'Available'],
      ['', TRUE, 'Available'],
      [new NullDateTime(), TRUE, 'Available'],
      [NULL, FALSE, 'Available'],
      ['', FALSE, 'Available'],
      [new NullDateTime(), TRUE, 'Available'],
      [new ConcreteDateTime($future_date), FALSE, 'Available'],
      [
        new ConcreteDateTime($future_date),
        TRUE,
        sprintf('Available until %s', $future_date->format(self::SHORT_DATE_FORMAT)),
      ],
    ];
  }

  /**
   * Data provider for testGetUpcomingSummary.
   */
  public function getUpcomingSummaryProvider() {
    $future_date = new \DateTime('+1 week');

    return [
      [NULL, TRUE, 'Upcoming'],
      ['', TRUE, 'Upcoming'],
      [new NullDateTime(), TRUE, 'Upcoming'],
      [NULL, FALSE, 'Upcoming'],
      ['', FALSE, 'Upcoming'],
      [new NullDateTime(), TRUE, 'Upcoming'],
      [new ConcreteDateTime($future_date), FALSE, 'Upcoming'],
      [
        new ConcreteDateTime($future_date),
        TRUE,
        sprintf('Upcoming %s', $future_date->format(self::SHORT_DATE_FORMAT)),
      ],
    ];
  }

  /**
   * Data provider for testGetExpiredSummary.
   */
  public function getExpiredSummaryProvider() {
    $past_date = new \DateTime('-1 week');

    return [
      [NULL, TRUE, 'Expired'],
      ['', TRUE, 'Expired'],
      [new NullDateTime(), TRUE, 'Expired'],
      [NULL, FALSE, 'Expired'],
      ['', FALSE, 'Expired'],
      [new NullDateTime(), TRUE, 'Expired'],
      [new ConcreteDateTime($past_date), FALSE, 'Expired'],
      [
        new ConcreteDateTime($past_date),
        TRUE,
        sprintf('Expired on %s', $past_date->format(self::SHORT_DATE_FORMAT)),
      ],
    ];
  }

}
