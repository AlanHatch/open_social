<?php

namespace Drupal\social_group\Controller;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\entity\BulkFormEntityListBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for group content from GroupContentListBuilder.
 *
 * @ingroup group
 */
class SocialGroupContentListBuilder extends BulkFormEntityListBuilder {

  /**
   * The group to show the content for.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected $group;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $entityTypeManager;

  /**
   * The redirect destination.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The DateTime formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateTime;

  /**
   * Constructs a new GroupContentListBuilder object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_time
   *   The datetime formatter service.
   */
  public function __construct(FormBuilderInterface $form_builder, EntityTypeManagerInterface $entity_type_manager, RedirectDestinationInterface $redirect_destination, RouteMatchInterface $route_match, EntityTypeInterface $entity_type, DateFormatterInterface $date_time) {
    parent::__construct($entity_type, $entity_type_manager->getStorage($entity_type->id()), $entity_type_manager->getStorage('action'), $form_builder);
    $this->entityTypeManager = $entity_type_manager;
    $this->redirectDestination = $redirect_destination;
    // There should always be a group on the route for group content lists.
    $this->group = $route_match->getParameters()->get('group');
    $this->dateTime = $date_time;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('form_builder'),
      $container->get('entity_type.manager'),
      $container->get('redirect.destination'),
      $container->get('current_route_match'),
      $entity_type,
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery();
    $query->sort($this->entityType->getKey('id'));

    // Only show group content for the group on the route.
    $query->condition('gid', $this->group->id());
    $query->condition('type', 'group_membership', 'CONTAINS');

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'member' => $this->t('Member'),
      'organization' => $this->t('Organization'),
      'created' => $this->t('Joined date'),
      'group_role' => $this->t('Role'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\group\Entity\GroupInterface $entity */
    $created = $this->dateTime->format($entity->getCreatedTime(), 'social_long_date');

    // Alter Group Membership table rows.
    if ($entity->getContentPlugin()->getPluginId() == 'group_membership') {
      // Prepare group roles.
      $roles = [];
      foreach ($entity->group_roles->referencedEntities() as $group_role) {
        $roles[] = $group_role->label();
      }
      if (empty($roles)) {
        $roles[] = $this->t('Member');
      }
      $roles = implode(', ', $roles);

      // Get user profile.
      $profile = _social_group_get_member_profile($entity);
      if (!empty($profile)) {
        // EntityListBuilder sets the table rows using the #rows property, so we
        // need to add the render array using the 'data' key.
        $row['member'] = \Drupal::entityTypeManager()
          ->getViewBuilder('profile')
          ->view($profile, 'table');
        $row['organization'] = $profile->get('field_profile_organization')
          ->view(['label' => 'hidden']);
        $row['created']['#markup'] = $created;
        $row['group_role']['#markup'] = $roles;
      }
    }
    else {
      $row['member']['#markup'] = $entity->id();
      $row['organization'] = $entity->toLink()->toRenderable();
      $row['created']['#markup'] = $created;
    }
    if (isset($row)) {
      return $row + parent::buildRow($entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build[$this->entitiesKey]['#empty'] = $this->t('There are no members yet.');
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    /** @var \Drupal\group\Entity\GroupContentInterface $entity */
    $operations = parent::getDefaultOperations($entity);

    // Improve the edit and delete operation labels.
    if (isset($operations['edit'])) {
      $operations['edit']['title'] = $this->t('Edit');
    }
    if (isset($operations['delete'])) {
      $operations['delete']['title'] = $this->t('Remove');
    }

    // Slap on redirect destinations for the administrative operations.
    $destination = $this->redirectDestination->getAsArray();
    foreach ($operations as $key => $operation) {
      $operations[$key]['query'] = $destination;
    }

    // Add an operation to view the actual entity.
    if ($entity->getEntity()->access('view') && $entity->getEntity()->hasLinkTemplate('canonical')) {
      $operations['view'] = [
        'title' => $this->t('View'),
        'weight' => 101,
        'url' => $entity->getEntity()->toUrl('canonical'),
      ];
    }

    return $operations;
  }

}
