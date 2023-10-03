<?php

namespace Drupal\rocketship_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\rocketship_core\Plugin\Field\FieldType\ContentBlockTitleReplacement;
use Drupal\text\Plugin\Field\FieldFormatter\TextDefaultFormatter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'contentblock_title_replacement_formatter'
 * formatter.
 *
 * @FieldFormatter(
 *   id = "contentblock_title_replacement_formatter",
 *   label = @Translation("Title Replacement Formatter"),
 *   field_types = {
 *     "contentblock_title_replacement"
 *   }
 * )
 */
class ContentBlockTitleReplacementFormatter extends TextDefaultFormatter {

  /**
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $class = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $class->entityRepository = $container->get('entity.repository');
    $class->renderer = $container->get('renderer');
    $class->routeMatch = $container->get('current_route_match');
    $class->entityTypeManager = $container->get('entity_type.manager');
    return $class;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'wrapper_override' => 'nothing',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['wrapper_override'] = [
      '#type' => 'select',
      '#title' => $this->t('Override wrapper selection'),
      '#description' => $this->t('Select a tag to wrap this output in, overriding the selection made by the client.'),
      '#default_value' => $this->getSetting('wrapper_override'),
      '#options' => [
        'nothing' => $this->t('Nothing'),
        'h1' => $this->t('h1'),
        'h2' => $this->t('h2'),
        'h3' => $this->t('h3'),
        'h4' => $this->t('h4'),
        'h5' => $this->t('h5'),
        'h6' => $this->t('h6'),
        'span' => $this->t('span'),
      ],
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $summary[] = t('Wrapper override: @override', ['@override' => $this->getSetting('wrapper_override')]);

    return $summary;
  }

  /**
   * Fetch node object from the current route.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The currently viewed node object.
   */
  protected function fetchNodeFromRoute(): ?NodeInterface {
    $node = $this->routeMatch->getParameter('node');

    // Get node from section storage if node is not available in the route.
    if (!$node) {
      $subject = $this->routeMatch->getRawParameter('section_storage') ?? '';
      preg_match('/node\.([0-9]+)/', $subject, $matches);
      if (!empty($matches[1])) {
        $node = $matches[1];
      }
    }

    if ($node && !is_object($node)) {
      $node = $this->entityTypeManager->getStorage('node')
        ->load($node);
    }

    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    // Note: don't trust the passed langcode, it is for the block which may
    // always be EN, it may match the current language, it may be anything
    // so don't use it, rely on entity repository for the node itself.
    // If the title is being replaced, the correct value is already inside
    // $items anyway.
    $route_name = $this->routeMatch->getRouteName();
    foreach ($items as $delta => $item) {
      // Grab tag that the client chose.
      $tag = $item->wrapper ?? 'h1';
      // Make sure it's a legal choice.
      if (!in_array($tag, ContentBlockTitleReplacement::getPossibleOptions())) {
        $tag = 'h1';
      }
      if ($this->getSetting('wrapper_override') !== 'nothing') {
        $tag = $this->getSetting('wrapper_override');
      }

      $value = $item->value;
      $node = $this->fetchNodeFromRoute();

      // Show the current node title.
      if (!$item->replace && $node instanceof NodeInterface) {
        switch ($route_name) {
          case 'entity.node.canonical':
            $node = $this->entityRepository->getCanonical('node', $node->id());
            $node = $this->entityRepository->getTranslationFromContext($node);
            $value = $node->getTitle();
            $this->renderer->addCacheableDependency($elements, $node);
            break;

          case 'entity.node.revision':
            $revision_id = $this->routeMatch
              ->getParameter('node_revision');

            if ($revision_id instanceof NodeInterface) {
              $node = $revision_id;
            }
            else {
              $node = $this->entityTypeManager
                ->getStorage('node')
                ->loadRevision($revision_id);
            }

            $node = $this->entityRepository->getTranslationFromContext($node);
            $value = $node->getTitle();
            $this->renderer->addCacheableDependency($elements, $node);
            break;

          case (bool) preg_match('/layout_builder.*/', $route_name):
          case 'entity.node.latest_version':
            $node = $this->entityRepository->getActive('node', $node->id());
            $node = $this->entityRepository->getTranslationFromContext($node);
            $value = $node->getTitle();
            $this->renderer->addCacheableDependency($elements, $node);
            break;

          case 'diff.revisions_diff':
            $revision_id = $this->routeMatch
              ->getParameter('right_revision');
            $node = $this->entityTypeManager
              ->getStorage('node')
              ->loadRevision($revision_id);
            $node = $this->entityRepository->getTranslationFromContext($node);
            $value = $node->getTitle();
            $this->renderer->addCacheableDependency($elements, $node);
            break;

          default:
            $value = $this->t('Placeholder for replacement title');
            // Don't cache.
            $this->renderer->addCacheableDependency($elements, new \stdClass());
            break;
        }
      }

      // Fallback value.
      if (!$node && !$item->replace && !$value) {
        $value = $this->t('Placeholder for replacement title');
        // Don't cache.
        $this->renderer->addCacheableDependency($elements, new \stdClass());
      }

      $elements[$delta] = [
        '#prefix' => '<' . $tag . '>',
        '#suffix' => '</' . $tag . '>',
        '#markup' => $value,
        '#allowed_tags' => [
          'em',
          'strong',
        ],
      ];
    }

    return $elements;
  }

}
