<?php

namespace Drupal\yusaopeny_ymca360;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for the YMCA360 Mapping entity type.
 */
class MappingListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['y360id'] = $this->t('YMCA360 ID');
    $header['session'] = $this->t('Session');
    $header['location'] = $this->t('Location');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\yusaopeny_ymca360\Entity\Y360Mapping $entity*/
    $row['id'] = $entity->id();
    $row['y360id'] = $entity->getY360Id();
    $row['session'] = $entity->getSession()->toLink();
    $row['location'] = $entity->getLocation()->toLink();
    return $row + parent::buildRow($entity);
  }

}
