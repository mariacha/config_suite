<?php

namespace Drupal\config_suite;

use Drupal\system\SystemConfigSubscriber;
use Drupal\Core\Config\ConfigImporterEvent;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;

/**
 * System Config subscriber.
 */
class ConfigSuiteSubscriber extends SystemConfigSubscriber {
  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['onConfigSave', 255];
    $events[ConfigEvents::DELETE][] = ['onConfigDelete', 255];
    return $events;
  }

  /**
   * Ignores the check that the configuration synchronization is from the same site.
   *
   * This event listener blocks the check that the system.site:uuid's in the source and
   * target match to prevent the error "Site UUID in source storage does not match the target storage."
   *
   * @param ConfigImporterEvent $event
   *   The config import event.
   */
  public function onConfigImporterValidateSiteUUID(ConfigImporterEvent $event) {
    $event->stopPropagation();

    return true;
  }

  public function onConfigSave(ConfigCrudEvent $event) {
    $this->updateConfig($event, ConfigEvents::SAVE);
  }

  public function onConfigDelete(ConfigCrudEvent $event) {
    $this->updateConfig($event, ConfigEvents::DELETE);
  }

  /**
   * Almost exactly the same actions should happen on save and delete.
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   * @param string $method
   */
  private function updateConfig(ConfigCrudEvent $event, $method) {
    $config = \Drupal::config('config_suite.settings');
    if (!$config->get('automatic_export')) {
      return;
    }

    // Get our storage settings.
    $sync_storage = \Drupal::service('config.storage.sync');
    $active_storage = \Drupal::service('config.storage');

    // Find out which config was saved.
    $config = $event->getConfig();
    $changed_config = $config->getName();
    if ($method == ConfigEvents::DELETE) {
      $sync_storage->delete($changed_config);
    }
    else {
      $sync_storage->write($changed_config, $active_storage->read($changed_config));
    }

    // Export configuration collections.
    foreach ($active_storage->getAllCollectionNames() as $collection) {
      $active_collection = $active_storage->createCollection($collection);
      $sync_collection = $sync_storage->createCollection($collection);
      $sync_collection->write($changed_config, $active_collection->read($changed_config));
    }
  }
}