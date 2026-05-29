<?php

/** @var yii\web\View       $this     */
/** @var app\models\Bookmarks $bookmark */
/** @var app\models\Coins     $coin     */

use yii\helpers\Html;
use yii\widgets\ActiveForm;

echo Yii::$app->ViewUtils->getAdminSideBarLinks();
echo ' - ' . Html::a($coin->name, ['/admin/coinwallet', 'id' => $coin->id]);
echo ' &mdash; Bookmark: ' . Html::encode($bookmark->label) . '<br/><br/>';

$form = ActiveForm::begin(['options' => ['class' => 'uniForm']]);

echo '<fieldset class="inlineLabels">';

echo $form->field($bookmark, 'label')
    ->textInput(['maxlength' => 32]);

echo $form->field($bookmark, 'address')
    ->textInput(['maxlength' => 128]);

echo '</fieldset>';

echo Html::submitButton('Save', ['class' => 'submitButton ui-button ui-corner-all ui-widget']);

ActiveForm::end();
