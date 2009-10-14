<?php
/**
 * CModelBehavior class file.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link http://www.yiiframework.com/
 * @copyright Copyright &copy; 2008-2009 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

/**
 * CModelBehavior is a base class for behaviors that are attached to a model component.
 * The model should extend from {@link CModel} or its child classes.
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @version $Id: CModelBehavior.php 1082 2009-06-01 12:03:00Z qiang.xue $
 * @package system.base
 * @since 1.0.2
 */
 
Prado::using('System.Base.TBehavior');
 
class TModelBehavior extends TBehavior
{
	/**
	 * Declares events and the corresponding event handler methods.
	 * The default implementation returns 'onBeforeValidate' and 'onAfterValidate' events and handlers.
	 * If you override this method, make sure you merge the parent result to the return value.
	 * @return array events (array keys) and the corresponding event handler methods (array values).
	 * @see CBehavior::events
	 */
	public function events()
	{
		return array(
			'onBeforeValidate'=>'beforeValidate',
			'onAfterValidate'=>'afterValidate',
		);
	}

	/**
	 * Responds to {@link CModel::onBeforeValidate} event.
	 * Overrides this method if you want to handle the corresponding event of the {@link owner}.
	 * You may set {@link CModelEvent::isValid} to be false if you want to stop the current validation process.
	 * @param CModelEvent event parameter
	 */
	public function beforeValidate($event)
	{
	}

	/**
	 * Responds to {@link CModel::onAfterValidate} event.
	 * Overrides this method if you want to handle the corresponding event of the {@link owner}.
	 * @param CEvent event parameter
	 */
	public function afterValidate($event)
	{
	}
}