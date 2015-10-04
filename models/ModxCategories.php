<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "modx_categories".
 *
 * @property string $id
 * @property string $parent
 * @property string $category
 */
class ModxCategories extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'modx_categories';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['parent'], 'integer'],
            [['category'], 'string', 'max' => 45],
            [['parent', 'category'], 'unique', 'targetAttribute' => ['parent', 'category'], 'message' => 'The combination of Parent and Category has already been taken.']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'parent' => 'Parent',
            'category' => 'Category',
        ];
    }
}
