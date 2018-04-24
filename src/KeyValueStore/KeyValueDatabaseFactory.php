<?php

namespace Drupal\media_mpx\KeyValueStore;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\KeyValueStore\DatabaseStorage;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/**
 * Defines the key/value store factory for the database backend.
 */
class KeyValueDatabaseFactory implements KeyValueFactoryInterface {

  /**
   * The serialization class to use.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected $serializer;

  /**
   * The database connection to use.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The table to use for storage.
   *
   * @var string
   */
  protected $table;

  /**
   * Constructs this factory object.
   *
   * @param \Drupal\Component\Serialization\SerializationInterface $serializer
   *   The serialization class to use.
   * @param \Drupal\Core\Database\Connection $connection
   *   The Connection object containing the key-value tables.
   * @param string $table
   *   The table to use when storing data.
   */
  public function __construct(SerializationInterface $serializer, Connection $connection, string $table) {
    $this->serializer = $serializer;
    $this->connection = $connection;
    $this->table = $table;
  }

  /**
   * {@inheritdoc}
   */
  public function get($collection) {
    return new DatabaseStorage($collection, $this->serializer, $this->connection, $this->table);
  }

}
