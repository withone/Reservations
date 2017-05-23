<?php
/**
 * ReservationWorkflowBehavior.php
 *
 * @author   Ryuji AMANO <ryuji@ryus.co.jp>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('WorkflowBehavior', 'Workflow.Model/Behavior');

class ReservationWorkflowBehavior extends WorkflowBehavior {
/**
 * beforeValidate is called before a model is validated, you can use this callback to
 * add behavior validation rules into a models validate array. Returning false
 * will allow you to make the validation fail.
 *
 * @param Model $model Model using this behavior
 * @param array $options Options passed from Model::save().
 * @return mixed False or null will abort the operation. Any other result will continue.
 * @see Model::save()
 */
	public function beforeValidate(Model $model, $options = array()) {
		// statusのバリデーションはスルー
	}

/**
 * Get workflow conditions
 *
 * @param Model $model Model using this behavior
 * @param array $conditions Model::find conditions default value
 * @return array Conditions data
 */
	public function getWorkflowConditions(Model $model, $conditions = array()) {
		$this->log(var_export(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), true), 'debug');

		// TODO 施設予約にあわせて変更する
		if (Current::permission('content_editable')) {
			$activeConditions = array();
			$latestConditons = array(
				$model->alias . '.is_latest' => true,
			);
		} elseif (Current::permission('content_creatable')) {
			$activeConditions = array(
				$model->alias . '.is_active' => true,
				$model->alias . '.created_user !=' => Current::read('User.id'),
			);
			// 時限公開条件追加
			if ($model->hasField('public_type')) {
				$publicTypeConditions = $this->_getPublicTypeConditions($model);
				$activeConditions[] = $publicTypeConditions;
			}
			$latestConditons = array(
				$model->alias . '.is_latest' => true,
				$model->alias . '.created_user' => Current::read('User.id'),
			);
		} else {
			// 時限公開条件追加
			$activeConditions = array(
				$model->alias . '.is_active' => true,
			);
			if ($model->hasField('public_type')) {
				$publicTypeConditions = $this->_getPublicTypeConditions($model);
				$activeConditions[] = $publicTypeConditions;
			}
			$latestConditons = array();
		}

		if ($model->hasField('language_id')) {
			if (Current::read('Plugin.is_m17n') === false && $model->hasField('is_origin')) {
				$langConditions = array(
					$model->alias . '.is_origin' => true,
				);
			} elseif ($model->hasField('is_translation')) {
				$langConditions = array(
					'OR' => array(
						$model->alias . '.language_id' => Current::read('Language.id'),
						$model->alias . '.is_translation' => false,
					)
				);
			} else {
				$langConditions = array(
					$model->alias . '.language_id' => Current::read('Language.id'),
				);
			}
		} else {
			$langConditions = array();
		}

		$conditions = Hash::merge(
			array(
				$langConditions,
				array('OR' => array($activeConditions, $latestConditons))
			),
			$conditions
		);

		return $conditions;
	}

/**
 * Get workflow contents
 *
 * @param Model $model Model using this behavior
 * @param string $type Type of find operation (all / first / count / neighbors / list / threaded)
 * @param array $query Option fields (conditions / fields / joins / limit / offset / order / page / group / callbacks)
 * @return array Conditions data
 */
	public function getWorkflowContents(Model $model, $type, $query = array()) {
		//$this->log(var_export(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), true), 'debug');

		$query = Hash::merge(array(
			'recursive' => -1,
			'conditions' => $this->getWorkflowConditions($model)
		), $query);

		return $model->find($type, $query);
	}

/**
 * コンテンツの閲覧権限があるかどうかのチェック
 * - 閲覧権限あり(content_readable)
 *
 * @param Model $model Model using this behavior
 * @return bool true:閲覧可、false:閲覧不可
 */
	public function canReadWorkflowContent(Model $model) {
		//$this->log(var_export(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), true), 'debug');
		throw new Exception(__CLASS__ . "::" . __METHOD__);
		return Current::permission('content_readable');
	}

/**
 * コンテンツの作成権限があるかどうかのチェック
 * - 作成権限あり(content_creatable)
 *
 * @param Model $model Model using this behavior
 * @return bool true:作成可、false:作成不可
 */
	public function canCreateWorkflowContent(Model $model) {
		//$this->log(var_export(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), true), 'debug');
		throw new Exception(__CLASS__ . "::" . __METHOD__);
		return Current::permission('content_creatable');
	}

/**
 * コンテンツの編集権限があるかどうかのチェック
 * - 編集権限あり(content_editable)
 * - 自分自身のコンテンツ
 *
 * @param Model $model Model using this behavior
 * @param array $data コンテンツデータ
 * @return bool true:編集可、false:編集不可
 */
	public function canEditWorkflowContent(Model $model, $data) {
		//$this->log(var_export(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), true), 'debug');
		throw new Exception(__CLASS__ . "::" . __METHOD__);

		if (Current::permission('content_editable')) {
			return true;
		}
		if (! isset($data[$model->alias])) {
			$data[$model->alias] = $data;
		}
		if (! isset($data[$model->alias]['created_user'])) {
			return false;
		}
		return ((int)$data[$model->alias]['created_user'] === (int)Current::read('User.id'));
	}

/**
 * コンテンツの公開権限があるかどうかのチェック
 * - 公開権限あり(content_publishable) and 編集権限あり(content_editable)
 * - 自分自身のコンテンツ＋一度も公開されていない
 *
 * @param Model $model Model using this behavior
 * @param array $data コンテンツデータ
 * @return bool true:削除可、false:削除不可
 */
	public function canDeleteWorkflowContent(Model $model, $data) {
		//$this->log(var_export(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), true), 'debug');
		throw new Exception(__CLASS__ . "::" . __METHOD__);

		if (! $this->canEditWorkflowContent($model, $data)) {
			return false;
		}
		if (Current::permission('content_publishable')) {
			return true;
		}
		if (! isset($data[$model->alias])) {
			$data[$model->alias] = $data;
		}

		$conditions = array(
			'is_active' => true,
		);
		if ($model->hasField('key') && isset($data[$model->alias]['key'])) {
			$conditions['key'] = $data[$model->alias]['key'];
		} else {
			return false;
		}

		$count = $model->find('count', array(
			'recursive' => -1,
			'conditions' => $conditions
		));
		return ((int)$count === 0);
	}

/**
 * 時限公開のconditionsを返す
 *
 * @param Model $model 対象モデル
 * @return array
 */
	protected function _getPublicTypeConditions(Model $model) {
		//$this->log(var_export(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), true), 'debug');

		$netCommonsTime = new NetCommonsTime();
		$limitedConditions = array();
		$limitedConditions[$model->alias . '.public_type'] = self::PUBLIC_TYPE_LIMITED;
		if ($model->hasField('publish_start')) {
			$limitedConditions[] = array(
				'OR' => array(
					$model->alias . '.publish_start <=' => $netCommonsTime->getNowDatetime(),
					$model->alias . '.publish_start' => null,
				)
			);
		}
		if ($model->hasField('publish_end')) {
			$limitedConditions[] = array(
				'OR' => array(
					$model->alias . '.publish_end >=' => $netCommonsTime->getNowDatetime(),
					$model->alias . '.publish_end' => null,
				)
			);
		}

		$publicTypeConditions = array(
			'OR' => array(
				$model->alias . '.public_type' => self::PUBLIC_TYPE_PUBLIC,
				$limitedConditions,
			)
		);
		return $publicTypeConditions;
	}

}