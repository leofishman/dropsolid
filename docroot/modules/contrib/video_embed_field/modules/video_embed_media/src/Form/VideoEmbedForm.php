<?php

namespace Drupal\video_embed_media\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\media_library\Form\AddFormBase;
use Drupal\media_library\MediaLibraryUiBuilder;
use Drupal\media_library\OpenerResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\video_embed_media\Plugin\media\Source\VideoEmbedField;
use Drupal\video_embed_field\ProviderManagerInterface;

/**
 * Creates a form to create media entities from video embed field source.
 */
class VideoEmbedForm extends AddFormBase {

  /**
   * The embed provider plugin manager.
   *
   * @var \Drupal\video_embed_field\ProviderManagerInterface
   */
  protected $providerManager;

  /**
   * Constructs a new VideoEmbedForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\media_library\MediaLibraryUiBuilder $library_ui_builder
   *   The media library UI builder.
   * @param \Drupal\media_library\OpenerResolverInterface $opener_resolver
   *   The opener resolver.
   * @param \Drupal\video_embed_field\ProviderManagerInterface $provider_manager
   *   Video embed field provider manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MediaLibraryUiBuilder $library_ui_builder, OpenerResolverInterface $opener_resolver = NULL, ProviderManagerInterface $provider_manager = NULL) {
    parent::__construct($entity_type_manager, $library_ui_builder, $opener_resolver);
    $this->providerManager = $provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('media_library.ui_builder'),
      $container->get('media_library.opener_resolver'),
      $container->get('video_embed_field.provider_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->getBaseFormId() . '_video_embed';
  }

  /**
   * {@inheritdoc}
   */
  protected function getMediaType(FormStateInterface $form_state) {
    if ($this->mediaType) {
      return $this->mediaType;
    }

    $media_type = parent::getMediaType($form_state);
    if (!$media_type->getSource() instanceof VideoEmbedField) {
      throw new \InvalidArgumentException('Can only add media types which use a video embed field source plugin.');
    }
    return $media_type;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildInputElement(array $form, FormStateInterface $form_state) {

    $media_type = $this->getMediaType($form_state);
    $field_config = $media_type->getSource()->getSourceFieldDefinition($media_type);
    $allowed_providers = $field_config->getSetting('allowed_providers');
    $providers = [];
    foreach ($this->providerManager->getProvidersOptionList() as $id => $title) {
      if (in_array($id, $allowed_providers, TRUE)) {
        $providers[] = $title->render();
      }
    }

    // Add a container to group the input elements for styling purposes.
    $form['container'] = [
      '#type' => 'container',
    ];

    $form['container']['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Add @type via URL', [
        '@type' => $this->getMediaType($form_state)->label(),
      ]),
      '#description' => $this->t('Allowed providers: @providers.', [
        '@providers' => implode(', ', $providers),
      ]),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => 'https://',
      ],
    ];

    $form['container']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#button_type' => 'primary',
      '#validate' => ['::validateUrl'],
      '#submit' => ['::addButtonSubmit'],
      '#ajax' => [
        'callback' => '::updateFormCallback',
        'wrapper' => 'media-library-wrapper',
        'url' => Url::fromRoute('media_library.ui'),
        'options' => [
          'query' => $this->getMediaLibraryState($form_state)->all() + [
            FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
          ],
        ],
      ],
    ];

    // Core admin themes like Claro and Seven add styling classes to the
    // upload forms via specific hook_form_FORM_ID_alter functions that
    // target core file uploads and oembed Media Source types. However,
    // these styles aren't applied to forms from 3rd party Media Source
    // types, therefore we add these classes manually for a piggyback ride.
    $form['#attributes']['class'][] = 'media-library-add-form--video-embed';
    $form['#attributes']['class'][] = 'media-library-add-form--oembed';
    $form['container']['#attributes']['class'][] = 'media-library-add-form__input-wrapper';
    $form['container']['url']['#attributes']['class'][] = 'media-library-add-form-oembed-url';
    $form['container']['submit']['#attributes']['class'][] = 'media-library-add-form-oembed-submit';

    return $form;
  }

  /**
   * Validates the URL.
   *
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function validateUrl(array &$form, FormStateInterface $form_state) {
    $url = $form_state->getValue('url');
    if ($url) {
      $media_type = $this->getMediaType($form_state);
      $field_config = $media_type->getSource()->getSourceFieldDefinition($media_type);
      $allowed_providers = $field_config->getSetting('allowed_providers');
      $providers = [];
      foreach ($this->providerManager->getProvidersOptionList() as $id => $title) {
        if (in_array($id, $allowed_providers, TRUE)) {
          $providers[] = $id;
        }
      }
      $provider = $this->providerManager->loadProviderFromInput($url);
      if (empty($provider)) {
        $form_state->setErrorByName('url', $this->t('Could not find a video provider to handle the given URL.'));
      }
      elseif (!in_array($provider->getPluginId(), $providers, TRUE)) {
        $form_state->setErrorByName('url',
          $this->t('Videos from %provider are not permitted for this video embed field. Allowed providers: @providers.', [
            '%provider' => $provider->getPluginDefinition()['title'],
            '@providers' => implode(', ', $providers),
          ])
        );
      }
    }
  }

  /**
   * Submit handler for the add button.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addButtonSubmit(array $form, FormStateInterface $form_state) {
    $this->processInputValues([$form_state->getValue('url')], $form, $form_state);
  }

}
