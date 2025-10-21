<?php

declare(strict_types=1);

namespace Joomla\Plugin\System\OtarolaMulticat;

use JsonException;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\Exception\DatabaseExceptionInterface;
use Joomla\Plugin\System\OtarolaMulticat\Helper\ArticlesModelOverrideShim;
use Joomla\Plugin\System\OtarolaMulticat\Helper\MulticatHelper;
use Joomla\Utilities\ArrayHelper;
use RuntimeException;
use Throwable;

use function defined;
use function htmlspecialchars;
use function implode;
use function is_array;
use function print_r;

if (!defined('_JEXEC')) {
    return;
}

/**
 * System plugin enabling multi-category assignments for articles.
 */
final class PlgSystemOtarola_multicat extends CMSPlugin
{
    protected CMSApplicationInterface $app;

    protected DatabaseInterface $db;

    protected bool $autoloadLanguage = true;

    private bool $loggerRegistered = false;

    /**
     * Initialise runtime behaviours.
     */
    public function onAfterInitialise(): void
    {
        if ($this->app->isClient('site')) {
            ArticlesModelOverrideShim::register();
        }
    }

    /**
     * Injects additional category fields into the article form.
     */
    public function onContentPrepareForm(Form $form, mixed $data): void
    {
        if ($form->getName() !== 'com_content.article') {
            return;
        }

        if ($form->getField('additional_categories', 'otarola_multicat') !== null) {
            return;
        }

        try {
            $options = MulticatHelper::getContentCategories($this->db);
        } catch (Throwable $exception) {
            $this->logDebug('Failed to load category options.', ['error' => $exception->getMessage()]);

            return;
        }

        if ($options === []) {
            return;
        }

        $xml = $this->buildFieldXml($options);

        try {
            $form->load($xml);
        } catch (RuntimeException $exception) {
            $this->logDebug('Failed to inject additional categories fieldset.', ['error' => $exception->getMessage()]);
        }
    }

    /**
     * Prefills form data with stored selections.
     */
    public function onContentPrepareData(string $context, object $data): void
    {
        if ($context !== 'com_content.article') {
            return;
        }

        if (!property_exists($data, 'otarola_multicat') || !is_array($data->otarola_multicat)) {
            $data->otarola_multicat = [];
        }

        $selected = [];

        if (isset($data->otarola_multicat['additional_categories']) && is_array($data->otarola_multicat['additional_categories'])) {
            $selected = ArrayHelper::toInteger($data->otarola_multicat['additional_categories']);
        } elseif (!empty($data->id)) {
            try {
                $selected = MulticatHelper::getAdditionalCategoryIds($this->db, (int) $data->id);
            } catch (Throwable $exception) {
                $this->logDebug('Failed to load stored additional categories.', ['error' => $exception->getMessage()]);
            }
        } else {
            $userState = $this->app->getUserState('com_content.edit.article.data', []);

            if (isset($userState['otarola_multicat']['additional_categories'])) {
                $selected = ArrayHelper::toInteger((array) $userState['otarola_multicat']['additional_categories']);
            }
        }

        $data->otarola_multicat['additional_categories'] = array_values(array_unique(array_filter(
            $selected,
            static fn (int $id): bool => $id > 0
        )));
    }

    /**
     * Persists additional category selections.
     */
    public function onContentAfterSave(string $context, object $article, bool $isNew): void
    {
        if ($context !== 'com_content.article') {
            return;
        }

        if (empty($article->id)) {
            return;
        }

        $posted = $this->app->input->get('jform', [], 'array');
        $selected = [];

        if (isset($posted['otarola_multicat']['additional_categories'])) {
            $selected = ArrayHelper::toInteger((array) $posted['otarola_multicat']['additional_categories']);
        }

        $primaryCategoryId = property_exists($article, 'catid') ? (int) $article->catid : 0;

        $filtered = array_values(array_unique(array_filter(
            $selected,
            static fn (int $id): bool => $id > 0 && $id !== $primaryCategoryId
        )));

        try {
            MulticatHelper::saveAdditionalCategories($this->db, (int) $article->id, $filtered);
        } catch (DatabaseExceptionInterface|RuntimeException $exception) {
            $this->logDebug('Failed to persist additional categories.', ['error' => $exception->getMessage()]);
        }
    }

    /**
     * Ensures the plugin logger is configured and records debugging output when enabled.
     */
    private function logDebug(string $message, array $context = []): void
    {
        if (!$this->params->get('enable_logging')) {
            return;
        }

        $this->registerLogger();

        if ($context !== []) {
            try {
                $message .= ' ' . json_encode($context, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                $message .= ' ' . print_r($context, true);
            }
        }

        Log::add($message, Log::INFO, 'plg_system_otarola_multicat');
    }

    private function registerLogger(): void
    {
        if ($this->loggerRegistered) {
            return;
        }

        Log::addLogger(
            [
                'text_file' => 'otarola_multicat.php',
                'text_entry_format' => '{DATE} {TIME} {MESSAGE}',
            ],
            Log::INFO,
            ['plg_system_otarola_multicat']
        );

        $this->loggerRegistered = true;
    }

    /**
     * Builds the XML snippet used to inject the additional categories fieldset.
     *
     * @param  array<int, array<string, mixed>>  $options
     */
    private function buildFieldXml(array $options): string
    {
        $fieldsetLabel = htmlspecialchars(
            Text::_('PLG_SYSTEM_OTAROLA_MULTICAT_FIELDSET_LABEL'),
            ENT_COMPAT,
            'UTF-8'
        );
        $fieldLabel = htmlspecialchars(
            Text::_('PLG_SYSTEM_OTAROLA_MULTICAT_FIELD_ADDITIONAL_CATEGORIES'),
            ENT_COMPAT,
            'UTF-8'
        );
        $fieldDescription = htmlspecialchars(
            Text::_('PLG_SYSTEM_OTAROLA_MULTICAT_FIELD_ADDITIONAL_CATEGORIES_DESC'),
            ENT_COMPAT,
            'UTF-8'
        );

        $optionMarkup = [];

        foreach ($options as $option) {
            $value = (int) ($option['value'] ?? 0);
            $text = htmlspecialchars((string) ($option['text'] ?? ''), ENT_COMPAT, 'UTF-8');
            $optionMarkup[] = sprintf('<option value="%d">%s</option>', $value, $text);
        }

        $optionsXml = implode('', $optionMarkup);

        return <<<XML
<form>
    <fields name="otarola_multicat">
        <fieldset name="otarola_multicat" label="{$fieldsetLabel}">
            <field name="additional_categories" type="checkboxes" multiple="true" label="{$fieldLabel}" description="{$fieldDescription}">
                {$optionsXml}
            </field>
        </fieldset>
    </fields>
</form>
XML;
    }
}
