<?php
/*
 * @package     Znatok Package
 * @subpackage  plg_znatok_content
 * @version     __DEPLOY_VERSION__
 * @author      Delo Design - delo-design.ru
 * @copyright   Copyright (c) 2021 Delo Design. All rights reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://delo-design.ru/
 */

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

class plgZnatokContent extends CMSPlugin
{
	/**
	 * Loads the application object.
	 *
	 * @var  CMSApplication
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected $app = null;

	/**
	 * Loads the database object.
	 *
	 * @var  JDatabaseDriver
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected $db = null;

	/**
	 * Affects constructor behavior.
	 *
	 * @var  boolean
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected $autoloadLanguage = true;

	/**
	 * Change com_content forms trigger.
	 *
	 * @param   Form   $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @throws  Exception
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function onContentPrepareForm($form, $data)
	{
		$formName = $form->getName();
		if ($formName === 'com_config.component' && $this->app->input->get('component') === 'com_content')
		{
			Form::addFormPath(__DIR__ . '/forms');
			$form->loadFile('config');
			Factory::getDocument()->addStyleDeclaration("#znatok .subform-repeatable {max-width: 300px}");
		}
	}

	/**
	 * Method to get url params for canonical and redirect.
	 *
	 * @param   Registry  $params  Znatok Component options.
	 *
	 * @return array|false
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function onZnatokDoublesProtection($params)
	{
		if ($this->app->input->get('option') === 'com_content')
		{
			$view   = $this->app->input->get('view');
			$id     = $this->app->input->getInt('id');
			$result = array(
				'link'              => false,
				'canonical'         => null,
				'redirect'          => null,
				'use_route'         => true,
				'canonical_allowed' => array(),
				'redirect_allowed'  => array()
			);

			$contentParams = ComponentHelper::getParams('com_content');
			JLoader::register('ContentHelperRoute', JPATH_SITE . '/components/com_content/helpers/route.php');

			if ($view === 'categories'
				&& ($contentParams->get('znatok_categories_doubles_canonical', 1) ||
					$contentParams->get('znatok_categories_doubles_redirect', 1)))
			{
				// Content Categories
				$link           = 'index.php?option=com_content&view=categories&id=' . $id;
				$result['link'] = true;
				if ($contentParams->get('znatok_categories_doubles_canonical', 1))
				{
					$result['canonical']         = $link;
					$result['canonical_allowed'] = ArrayHelper::getColumn(
						ArrayHelper::fromObject($contentParams->get('znatok_categories_doubles_canonical_allowed',
							new stdClass())), 'key');
				}
				if ($contentParams->get('znatok_categories_doubles_redirect', 1))
				{
					$result['redirect']         = $link;
					$result['redirect_allowed'] = ArrayHelper::getColumn(
						ArrayHelper::fromObject($contentParams->get('znatok_categories_doubles_redirect_allowed',
							new stdClass())), 'key');
				}
			}
			elseif ($view === 'category' && ($contentParams->get('znatok_category_doubles_canonical', 1) ||
					$contentParams->get('znatok_category_doubles_redirect', 1)))
			{
				// Category
				$link      = ContentHelperRoute::getCategoryRoute($id);
				$startLink = $link;
				$limit     = $this->getContentCategoryLimit();
				if ($offset = $this->app->input->getInt('start'))
				{
					$startLink .= '&start=' . floor($offset / $limit) * $limit;
				}

				$result['link'] = true;
				if ($contentParams->get('znatok_category_doubles_canonical', 1))
				{
					$result['canonical'] = ($contentParams->get('znatok_category_doubles_canonical_start', 1))
						? $startLink : $link;

					$result['canonical_allowed'] = ArrayHelper::getColumn(
						ArrayHelper::fromObject($contentParams->get('znatok_category_doubles_canonical_allowed',
							new stdClass())), 'key');
				}

				if ($contentParams->get('znatok_category_doubles_redirect', 1))
				{
					$result['redirect']         = $startLink;
					$result['redirect_allowed'] = ArrayHelper::getColumn(
						ArrayHelper::fromObject($contentParams->get('znatok_category_doubles_redirect_allowed',
							new stdClass())), 'key');
				}
			}
			elseif ($view == 'article' && ($contentParams->get('znatok_article_doubles_canonical', 1) ||
					$contentParams->get('znatok_article_doubles_redirect', 1)))
			{
				// Content Article
				$link = ContentHelperRoute::getArticleRoute($id, $this->app->input->getInt('catid'));

				$result['link'] = true;
				if ($contentParams->get('znatok_article_doubles_canonical', 1))
				{
					$result['canonical']         = $link;
					$result['canonical_allowed'] = ArrayHelper::getColumn(
						ArrayHelper::fromObject($contentParams->get('znatok_article_doubles_canonical_allowed',
							new stdClass())), 'key');
				}
				if ($contentParams->get('znatok_article_doubles_redirect', 1))
				{
					$result['redirect']         = $link;
					$result['redirect_allowed'] = ArrayHelper::getColumn(
						ArrayHelper::fromObject($contentParams->get('znatok_article_doubles_redirect_allowed',
							new stdClass())), 'key');
				}
			}

			if ($result['link']) return $result;
		}

		return false;
	}

	/**
	 * Method to get content category limit.
	 *
	 * @return int content category page items limit.
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected function getContentCategoryLimit()
	{
		/* @var $app SiteApplication */
		$app    = $this->app;
		$params = $app->getParams();
		if ($app->input->get('layout') === 'blog' || $params->get('layout_type') === 'blog')
		{
			$limit = $params->get('num_leading_articles') + $params->get('num_intro_articles');
		}
		else
		{
			$itemid = $app->input->get('id', 0, 'int') . ':'
				. $app->input->get('Itemid', 0, 'int');
			$limit  = (int) $app->getUserStateFromRequest('com_content.category.list.' . $itemid . '.limit',
				'limit', $params->get('display_num'), 'uint');
		}

		return $limit;
	}

	/**
	 * Add pagination meta data.
	 *
	 * @param   string    $context  The context of the content being passed to the plugin.
	 * @param   object   &$row      The item object.
	 * @param   mixed    &$params   The view params.
	 * @param   integer   $page     The 'page' number.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onContentPrepare($context, &$row, &$params, $page = 0)
	{
		if ($this->app->isClient('site'))
		{
			if ($context === 'com_content.category'
				&& $this->app->input->get('option') === 'com_content'
				&& $this->app->input->get('view') === 'category'
				&& !empty($row->introtext))
			{
				if ($paginationMeta = $this->getPaginationMeta())
				{
					$paginationMeta['items_title'][] = $row->title;
					if (empty($paginationMeta['category_title']))
					{
						$db                               = $this->db;
						$query                            = $db->getQuery(true)
							->select('title')
							->from($db->quoteName('#__categories'))
							->where('id = ' . $this->app->input->getInt('id'));
						$paginationMeta['category_title'] = $db->setQuery($query)->loadResult();
					}
					if (empty($paginationMeta['page']))
					{
						$page = 1;
						if ($offset = $this->app->input->getInt('start'))
						{
							$page += $offset / $this->getContentCategoryLimit();
						}
						$paginationMeta['page'] = $page;
					}

					Factory::getDocument()->addScriptOptions('plg_znatok_pagination_meta', $paginationMeta);
				}
			}
		}
	}

	/**
	 * Method to get content category pagination meta.
	 *
	 * @return array|false Data array on success, False on failure.
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function getPaginationMeta()
	{
		/* @var HtmlDocument $doc */
		$doc = Factory::getDocument();
		if ($doc->getType() !== 'html') return false;

		if (!$data = $doc->getScriptOptions('plg_znatok_pagination_meta'))
		{
			$data = array(
				'page'           => null,
				'category_title' => null,
				'items_title'    => array()
			);

			$doc->addScriptOptions('plg_znatok_pagination_meta', $data);
		}

		return $data;
	}

	/**
	 * Method to get pagination meta data for set.
	 *
	 * @return array|false Data array on success, False on failure.
	 *
	 * @since __DEPLOY_VERSION__
	 */
	public function onZnatokPaginationMeta()
	{
		if (
			$this->app->input->get('option') !== 'com_content'
			|| $this->app->input->get('view') !== 'category'
		) return false;

		if ($data = $this->getPaginationMeta())
		{
			if (empty($data['page']) || $data['page'] <= 1) return false;
			$contentParams = ComponentHelper::getParams('com_content');

			/* @var HtmlDocument $doc */
			$doc = Factory::getDocument();
			if ($doc->getType() !== 'html') return false;

			$result = array(
				'title'       => false,
				'description' => false,
			);

			// Set title
			if ($contentParams->get('znatok_category_pagination_title', 1))
			{
				$result['title'] = Text::sprintf('COM_ZNATOK_PAGINATION_TITLE', $doc->getTitle(), $data['page']);
			}

			// Set description
			if ($contentParams->get('znatok_category_pagination_description', 1))
			{
				$descriptionStart   = $data['category_title'] . ': ';
				$descriptionEnd     = ' ...';
				$descriptionEndShow = false;
				$descriptionTotal   = iconv_strlen($descriptionStart) + iconv_strlen($descriptionEnd);
				$descriptionMax     = 250;

				$descriptionMiddle = array();
				foreach ($data['items_title'] as $title)
				{
					$descriptionTotalNew = $descriptionTotal + iconv_strlen($title) + 2;
					if ($descriptionTotalNew <= $descriptionMax)
					{
						$descriptionTotal    = $descriptionTotalNew;
						$descriptionMiddle[] = $title;
					}
					else
					{
						$descriptionEndShow = true;
						break;
					}
				}

				$result['description'] = $descriptionStart . implode(', ', $descriptionMiddle);
				if ($descriptionEndShow)
				{
					$result['description'] .= $descriptionEnd;
				}
			}

			$doc->addScriptOptions('plg_znatok_pagination_meta', array());

			return $result;
		}

		return false;
	}
}
