<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;

use app\components\rpc\WalletRPC;
use app\models\Coins;

class AdminController extends Controller
{
    public $defaultAction='dashboard';

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'actions' => ['login'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
                'denyCallback' => function(){
                    return $this->goHome();
                }
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays Main Dashboard.
     *
     * @return Response|string
     */
    public function actionDashboard()
	{
		return $this->render('dashboard');
	}


    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        if ((!is_null(Yii::$app->user->identity)) && (Yii::$app->user->identity->is_admin)) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /* Dashboard sub-parts */
    public function actionCommon_results()
	{  
        return $this->renderPartial('common_results');
	}

    /////////////////////////////////////////////////
    /* generating data for graphs */

	public function actionGraph_assets_results()
	{
		return $this->renderPartial('results/graph_assets_results');
	}

	public function actionGraph_negative_results()
	{
		return $this->renderPartial('results/graph_negative_results');
	}

	public function actionGraph_profit_results()
	{
		return $this->renderPartial('results/graph_profit_results');
	}

    /////////////////////////////////////////////////

    public function actionGraph_market_balance()
	{
        $coinid = Yii::$app->getRequest()->getQueryParam('id');
		return $this->renderPartial('results/graph_market_balance', ['id' => $coinid]);
	}

    public function actionGraph_market_prices()
	{
        $coinid = Yii::$app->getRequest()->getQueryParam('id');
		return $this->renderPartial('results/graph_market_prices', ['id' => $coinid]);
	}

    /////////////////////////////////////////////////
    /* coin list and information */

    public function actionCoinlist()
	{
		return $this->render('coinlist');
	}

	public function actionCoin_create()
	{
		$coin = new Coins;
		$coin->txmessage = true;
		$coin->created = time();

		if (isset($_POST['Coins'])) {
            $coin->setAttributes($_POST['Coins'], false);
    
            if ($coin->validate() && $coin->save())
            {
                return $this->redirect(array('coinlist'));
            }
        }

		return $this->render('coin_update', array('coin'=>$coin, 'update'=>false));
	}

	public function actionCoin_update()
	{
        $coinid = (int) Yii::$app->getRequest()->getQueryParam('id');
		$coin = Coins::findOne($coinid);

        if (isset($_POST['Coins'])) {
            $coin->setAttributes($_POST['Coins'], false);
    
            if ($coin->validate() && $coin->save())
            {
                return $this->redirect(array('coinlist'));
            }
        }

		return $this->render('coin_update', array('coin'=>$coin, 'update'=>true));
	}

	/////////////////////////////////////////////////

	public function actionCoinwallets()
	{
		return $this->render('coinwallets');
	}

	public function actionCoinwallet_results()
	{
		return $this->renderPartial('coinwallet_results');
	}

    /////////////////////////////////////////////////

    public function actionCoinwallet()
	{
		return $this->render('coinwallet');
	}

    public function actionCoinwallet_details()
	{
		return $this->renderPartial('coinwallet_details');
	}

	/////////////////////////////////////////////////

	public function actionCoinwallet_create()
	{
		$coin = new db_coins;
		$coin->txmessage = true;
		$coin->created = time();
		$coin->index_avg = 1;
		$coin->difficulty = 1;
		$coin->installed = 1;
		$coin->visible = 1;

		$coin->lastblock = '';

		if(isset($_POST['Coins']))
		{
			$coin->setAttributes($_POST['Coins'], false);
			if($coin->validate() && $coin->save())
				return $this->redirect(array('coinwallets'));
		}

		return $this->render('coinwallet_form', array('update'=>false, 'coin'=>$coin));
	}

	public function actionCoinwallet_update()
	{
		$coin = Coins::findOne(['id' => (int) Yii::$app->getRequest()->getQueryParam('id')]);
		$txfee = $coin->txfee;

		if($coin && isset($_POST['Coins']))
		{
			$coin->setAttributes($_POST['Coins'], false);

			if($coin->validate() && $coin->save())
			{
				if($txfee != $coin->txfee)
				{
					$remote = new WalletRPC($coin);
					$remote->settxfee($coin->txfee);
				}
				return $this->redirect(array('coinwallet', 'id'=>$coin->id));
			}
		}

		return $this->render('coinwallet_form', array('update'=>true, 'coin'=>$coin));
	}

    /////////////////////////////////////////////////

	public function actionEarning()
	{
		return $this->render('earning');
	}

	public function actionEarning_results()
	{
		return $this->renderPartial('earning_results');
	}

	// called from the wallet
	public function actionClearearnings()
	{
		$coin = Coins::findOne(['id' => (int) Yii::$app->getRequest()->getQueryParam('id')]);
		if ($coin) {
			BackendClearEarnings($coin->id);
		}
		return $this->goback();
	}

    /////////////////////////////////////////////////

	public function actionExchange()
	{
		return $this->render('exchange');
	}

	public function actionExchange_results()
	{
		return $this->renderPartial('exchange_results');
	}

    /////////////////////////////////////////////////
   	public function actionMemcached()
	{
		return $this->render('memcached');
	}

    /////////////////////////////////////////////////

	public function actionConnections()
	{
		return $this->render('connections');
	}

	public function actionConnections_results()
	{
		return $this->renderPartial('connections_results');
	}


}