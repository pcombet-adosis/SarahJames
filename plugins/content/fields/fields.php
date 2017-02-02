<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.Fields
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die();

/**
 * Plug-in to show a custom field in eg an article
 * This uses the {fields ID} syntax
 *
 * @since  __DEPLOY_VERSION__
 */
class PlgContentFields extends JPlugin
{
	/**
	 * Plugin that shows a custom field
	 *
	 * @param   string  $context  The context of the content being passed to the plugin.
	 * @param   object  &$item    The item object.  Note $article->text is also available
	 * @param   object  &$params  The article params
	 * @param   int     $page     The 'page' number
	 *
	 * @return void
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function onContentPrepare($context, &$item, &$params, $page = 0)
	{
		// Don't run this plugin when the content is being indexed
		if ($context == 'com_finder.indexer')
		{
			return;
		}

		// Don't run if there is no text property (in case of bad calls) or it is empty
		if (empty($item->text))
		{
			return;
		}

		// Simple performance check to determine whether bot should process further
		if (strpos($item->text, 'field') === false)
		{
			return;
		}

		// Register FieldsHelper
		JLoader::register('FieldsHelper', JPATH_ADMINISTRATOR . '/components/com_fields/helpers/fields.php');

		// Search for {field ID} or {fieldgroup ID} tags and put the results into $matches.
		$regex = '/{(field|fieldgroup)\s+(.*?)}/i';
		preg_match_all($regex, $item->text, $matches, PREG_SET_ORDER);

		if ($matches)
		{
			$parts = FieldsHelper::extract($context);

			if (count($parts) < 2)
			{
				return;
			}

			$context    = $parts[0] . '.' . $parts[1];
			$fields     = FieldsHelper::getFields($context, $item, true);
			$fieldsById = array();
			$groups     = array();

			// Rearranging fields in arrays for easier lookup later.
			foreach ($fields as $field)
			{
				$fieldsById[$field->id]     = $field;
				$groups[$field->group_id][] = $field;
			}

			foreach ($matches as $i => $match)
			{
				// $match[0] is the full pattern match, $match[1] is the type (field or fieldgroup) and $match[2] the ID
				$id = (int) $match[2];

				if ($match[1] == 'field' && $id)
				{
					if (!isset($fieldsById[$id]))
					{
						continue;
					}

					$output = FieldsHelper::render(
						$context,
						'field.render',
						array(
							'item'    => $item,
							'context' => $context,
							'field'   => $fieldsById[$id]
						)
					);
				}
				else
				{
					if ($match[2] === '*')
					{
						$match[0]     = str_replace('*', '\*', $match[0]);
						$renderFields = $fields;
					}
					else
					{
						if (!isset($groups[$id]))
						{
							continue;
						}
						else
						{
							$renderFields = $groups[$id];
						}
					}

					$output = FieldsHelper::render(
						$context,
						'fields.render',
						array(
							'item'    => $item,
							'context' => $context,
							'fields'  => $renderFields
						)
					);
				}

				$item->text = preg_replace("|$match[0]|", addcslashes($output, '\\$'), $item->text, 1);
			}
		}
	}
}
