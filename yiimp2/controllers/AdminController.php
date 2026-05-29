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
use app\models\Markets;
use app\models\Mining;
use app\models\Algos;
use app\models\Bookmarks;
use app\models\Orders;
use app\services\CoinService;
use app\services\BlockService;
use yii\helpers\ArrayHelper;

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
    /* version breakdown */

    public function actionVersion(): string
    {
        $this->requireAdmin();
        return $this->render('version');
    }

    public function actionVersion_results(): string
    {
        $this->requireAdmin();
        $algo = Yii::$app->request->get('algo', '');
        if ($algo !== '') {
            Yii::$app->session->set('yaamp-algo', $algo);
        }
        return $this->renderPartial('version_results');
    }

    /////////////////////////////////////////////////
    /* worker list */

    public function actionWorker(): string
    {
        $this->requireAdmin();
        return $this->render('worker');
    }

    public function actionWorker_results(): string
    {
        $this->requireAdmin();
        $algo = Yii::$app->request->get('algo', '');
        if ($algo !== '') {
            Yii::$app->session->set('yaamp-algo', $algo);
        }
        return $this->renderPartial('worker_results');
    }

    /////////////////////////////////////////////////
    /* user list */

    public function actionUser(): string
    {
        $this->requireAdmin();
        return $this->render('user');
    }

    public function actionUser_results(): string
    {
        $this->requireAdmin();
        return $this->renderPartial('user_results');
    }

    /////////////////////////////////////////////////
    /* coin list and information */

    public function actionCoinlist()
	{
		$search   = trim(Yii::$app->request->get('q', ''));
		$pageSize = max(10, min(500, (int) Yii::$app->request->get('pageSize', 50)));

		$query = Coins::find()->orderBy(['created' => SORT_DESC]);

		if ($search !== '') {
			$query->andWhere(['or',
				['like', 'name',   $search],
				['like', 'symbol', $search],
				['like', 'algo',   $search],
			]);
		}

		// Compute totals over the full filtered set before the provider adds LIMIT
		$totalInstalled = (clone $query)->andWhere(['installed' => 1])->count();
		$totalActive    = (clone $query)->andWhere(['enable'    => 1])->count();

		$provider = new \yii\data\ActiveDataProvider([
			'query'      => $query,
			'pagination' => [
				'pageSize'      => $pageSize,
				'pageSizeParam' => 'pageSize',
			],
			'sort' => false,
		]);

		return $this->render('coinlist', [
			'provider'       => $provider,
			'totalInstalled' => (int) $totalInstalled,
			'totalActive'    => (int) $totalActive,
			'searchQuery'    => $search,
			'pageSize'       => $pageSize,
		]);
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
		$server      = Yii::$app->getRequest()->getQueryParam('server');
		$coins       = CoinService::getCoinWalletList($server);
		$mining      = Mining::find()->one();
		$coinIds     = array_map(fn($c) => $c->id, $coins);
		$blockCounts = CoinService::getBlockCountsByCoin($coinIds);

		return $this->renderPartial('coinwallet_results', [
			'coins'       => $coins,
			'mining'      => $mining,
			'blockCounts' => $blockCounts,
		]);
	}

    /////////////////////////////////////////////////

    public function actionCoinwallet()
	{
		return $this->render('coinwallet');
	}

    public function actionCoinwallet_details()
	{
		$id   = (int) Yii::$app->getRequest()->getQueryParam('id');
		$coin = Coins::findOne($id);
		if (!$coin) {
			return $this->goHome();
		}

		return $this->renderPartial('coinwallet_details', array_merge(
			['coin' => $coin],
			CoinService::getCoinWalletDetails($coin)
		));
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

		$algos = ArrayHelper::map(Algos::find()->all(), 'name', 'name');
		return $this->render('coinwallet_form', ['update' => false, 'coin' => $coin, 'algos' => $algos]);
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

		$algos = ArrayHelper::map(Algos::find()->all(), 'name', 'name');
		return $this->render('coinwallet_form', ['update' => true, 'coin' => $coin, 'algos' => $algos]);
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
    /* monsters — anomaly / high-activity user list */

    public function actionMonsters(): string
    {
        $this->requireAdmin();
        return $this->render('monsters');
    }

    /////////////////////////////////////////////////
    /* payments list */

    public function actionPayments(): string
    {
        $this->requireAdmin();
        return $this->render('payments');
    }

    public function actionPayments_results(): string
    {
        $this->requireAdmin();
        return $this->renderPartial('payments_results');
    }

    /** Restore a single user's failed payouts (no tx) to their balance. */
    public function actionCancelUserPayment(): \yii\web\Response
    {
        $this->requireAdmin();
        $id   = (int) Yii::$app->request->get('id');
        $user = \app\models\Accounts::findOne($id);
        if ($user) {
            (new \app\services\PaymentService())->cancelFailedPayment($user->id);
        }
        return $this->goBack();
    }

    /** Restore all failed payouts within 48 h for a coin back to user balances. */
    public function actionCancelUsersPayment(): \yii\web\Response
    {
        $this->requireAdmin();
        $id   = (int) Yii::$app->request->get('id');
        $coin = \app\models\Coins::findOne($id);

        if (!$coin) {
            Yii::$app->session->setFlash('error', 'Invalid coin id!');
            return $this->goBack();
        }

        $since   = time() - 48 * 3600;
        $failed  = \app\models\Payouts::find()
            ->where(['idcoin' => $coin->id])
            ->andWhere(['or', ['tx' => null], ['tx' => '']])
            ->andWhere(['>', 'time', $since])
            ->all();

        $totalAmount = 0.0;
        $count       = 0;

        foreach ($failed as $payout) {
            $user = \app\models\Accounts::findOne((int) $payout->account_id);
            if ($user) {
                $user->balance += (float) $payout->amount;
                if ($user->save()) {
                    $totalAmount += (float) $payout->amount;
                    $count++;
                }
            }
            $payout->delete();
        }

        $msg = $count
            ? "Restored {$count} failed tx(s) to user balances: {$totalAmount} {$coin->symbol}"
            : 'No failed txs found';
        Yii::$app->session->setFlash('success', $msg);
        return $this->goBack();
    }

    /////////////////////////////////////////////////

    public function actionBalances(): string
    {
        $this->requireAdmin();
        return $this->render('balances');
    }

    public function actionBalances_results(): string
    {
        $this->requireAdmin();
        $exch    = Yii::$app->request->get('exch', '');
        $markets = \app\models\Markets::find()
            ->where(['name' => $exch])
            ->orderBy(new \yii\db\Expression('(balance + ontrade) * price DESC'))
            ->all();
        $mining  = \app\models\Mining::find()->one() ?? new \app\models\Mining(['usdbtc' => 0]);
        return $this->renderPartial('balances_results', [
            'exch'    => $exch,
            'markets' => $markets,
            'mining'  => $mining,
        ]);
    }

    public function actionBalanceUpdate(): \yii\web\Response
    {
        $this->requireAdmin();
        $id     = (int) Yii::$app->request->get('market');
        $market = \app\models\Markets::findOne($id);
        if ($market) {
            \app\exchanges\ExchangeFactory::make($market->name)->updateMarkets();
            return $this->redirect(['/admin/balances', 'exch' => $market->name]);
        }
        return $this->goBack();
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

	public function actionClearmarket()
	{
		$this->requireAdmin();
		$market = Markets::findOne((int) Yii::$app->request->get('id'));
		if ($market) {
			$market->lastsent = null;
			$market->save(false);
		}
		return $this->redirect(Yii::$app->request->referrer ?: ['/admin/exchange']);
	}

	public function actionClearorder()
	{
		$this->requireAdmin();
		$order = Orders::findOne((int) Yii::$app->request->get('id'));
		if ($order) {
			$order->delete();
		}
		return $this->redirect(Yii::$app->request->referrer ?: ['/admin/exchange']);
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

	/////////////////////////////////////////////////
	/* coin peer management */

	public function actionCoinpeers()
	{
		$this->requireAdmin();
		$id   = (int) Yii::$app->request->get('id');
		$coin = Coins::findOne($id);
		if (!$coin) {
			return $this->goBack();
		}
		return $this->render('coin_peers', ['coin' => $coin]);
	}

	public function actionCoinpeerRemove()
	{
		$this->requireAdmin();
		$id   = (int) Yii::$app->request->get('id');
		$node = Yii::$app->request->get('node', '');
		$coin = Coins::findOne($id);

		if ($coin && $node) {
			$remote = new WalletRPC($coin);
			if ($coin->rpcencoding === 'DCR') {
				$res = $remote->node('disconnect', $node);
				if (!$res) {
					$remote->node('remove', $node);
				}
			} else {
				$res = $remote->addnode($node, 'remove');
				if (!$res && $remote->error) {
					Yii::$app->session->setFlash('error', "{$node} {$remote->error}");
				}
			}
		}

		return $this->redirect(Yii::$app->request->referrer ?: ['coinpeers', 'id' => $id]);
	}

	public function actionCoinpeerAdd()
	{
		$this->requireAdmin();
		if (!Yii::$app->request->isPost) {
			return $this->goBack();
		}
		$id   = (int) Yii::$app->request->get('id');
		$node = trim(Yii::$app->request->post('node', ''));
		$coin = Coins::findOne($id);

		if ($coin && $node) {
			$remote = new WalletRPC($coin);
			if ($coin->rpcencoding === 'DCR') {
				$remote->addnode($node, 'add');
				usleep(500_000);
				$remote->node('connect', $node);
				sleep(1);
			} else {
				$res = $remote->addnode($node, 'add');
				if (!$res && $remote->error) {
					Yii::$app->session->setFlash('error', "{$node} {$remote->error}");
				} else {
					sleep(1);
				}
			}
		}

		return $this->redirect(['coinpeers', 'id' => $id]);
	}

	/////////////////////////////////////////////////
	/* botnets — accounts mining from an abnormally high number of distinct IPs */

	public function actionBotnets(): string
	{
		$this->requireAdmin();
		return $this->render('botnets');
	}

	public function actionLoguser(): \yii\web\Response
	{
		$this->requireAdmin();
		$id   = (int) Yii::$app->request->get('id');
		$en   = (int) Yii::$app->request->get('en', 0);
		$user = \app\models\Accounts::findOne($id);
		if ($user) {
			$user->logtraffic = $en;
			$user->save();
		}
		return $this->goBack();
	}

	public function actionBlockuser(): \yii\web\Response
	{
		$this->requireAdmin();
		$wallet = trim((string) Yii::$app->request->get('wallet', ''));
		$user   = \app\models\Accounts::find()->where(['username' => $wallet])->one();
		if ($user) {
			$user->is_locked = true;
			$user->save();
		}
		return $this->goBack();
	}

	public function actionUnblockuser(): \yii\web\Response
	{
		$this->requireAdmin();
		$wallet = trim((string) Yii::$app->request->get('wallet', ''));
		$user   = \app\models\Accounts::find()->where(['username' => $wallet])->one();
		if ($user) {
			$user->is_locked = false;
			$user->save();
		}
		return $this->goBack();
	}

	public function actionBanuser(): \yii\web\Response
	{
		$this->requireAdmin();
		$id   = (int) Yii::$app->request->get('id');
		$user = \app\models\Accounts::findOne($id);
		if ($user) {
			$user->is_locked = true;
			$user->balance   = 0;
			$user->save();
		}
		return $this->goBack();
	}

	/////////////////////////////////////////////////

	public function actionBookmarkAdd(): mixed
	{
		$this->requireAdmin();
		$coin = Coins::findOne((int) Yii::$app->request->get('id'));
		if (!$coin) {
			return $this->goBack();
		}

		$bookmark          = new Bookmarks();
		$bookmark->idcoin  = $coin->id;

		if (Yii::$app->request->isPost) {
			$bookmark->setAttributes(Yii::$app->request->post('Bookmarks', []), false);
			if ($bookmark->save()) {
				return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
			}
		}

		return $this->render('bookmark', ['bookmark' => $bookmark, 'coin' => $coin]);
	}

	public function actionBookmarkEdit(): mixed
	{
		$this->requireAdmin();
		$bookmark = Bookmarks::findOne((int) Yii::$app->request->get('id'));
		if (!$bookmark) {
			Yii::$app->session->setFlash('error', 'invalid bookmark');
			return $this->goBack();
		}

		$coin = Coins::findOne($bookmark->idcoin);

		if (Yii::$app->request->isPost) {
			$bookmark->setAttributes(Yii::$app->request->post('Bookmarks', []), false);
			if ($bookmark->save()) {
				return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
			}
		}

		return $this->render('bookmark', ['bookmark' => $bookmark, 'coin' => $coin]);
	}

	public function actionBookmarkDel(): mixed
	{
		$this->requireAdmin();
		$bookmark = Bookmarks::findOne((int) Yii::$app->request->get('id'));
		if ($bookmark) {
			$bookmark->delete();
		}
		return $this->goBack();
	}

	public function actionBookmarkSend(): mixed
	{
		$this->requireAdmin();
		$bookmark = Bookmarks::findOne((int) Yii::$app->request->get('id'));
		if (!$bookmark) {
			return $this->goBack();
		}

		$coin   = Coins::findOne($bookmark->idcoin);
		$amount = (float) Yii::$app->request->get('amount', 0);

		$remote = new WalletRPC($coin);
		$info   = $remote->getinfo();

		if (!$info || empty($info['balance'])) {
			Yii::$app->session->setFlash('error', "not enough balance {$coin->name}");
			return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
		}

		$depositInfo = $remote->validateaddress($bookmark->address);
		if (!$depositInfo || empty($depositInfo['isvalid'])) {
			Yii::$app->session->setFlash('error', "invalid address for {$coin->name}, {$bookmark->address}");
			return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
		}

		$amount = round(min($amount, $info['balance'] - ($info['paytxfee'] ?? 0)), 8);

		$tx = $remote->sendtoaddress($bookmark->address, $amount);
		if (!$tx) {
			Yii::$app->session->setFlash('error', $remote->error);
			return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
		}

		$bookmark->lastused = time();
		$bookmark->save();

		(new BlockService())->updatePoolBalances($coin->id);

		return $this->redirect(['/admin/coinwallet', 'id' => $coin->id]);
	}

	/////////////////////////////////////////////////

	public function actionBenchdel(): \yii\web\Response
	{
		$this->requireAdmin();
		$id = (int) Yii::$app->request->get('id');
		if ($id > 0) {
			Yii::$app->db->createCommand('DELETE FROM benchmarks WHERE id = :id', [':id' => $id])->execute();
		}
		return $this->redirect(Yii::$app->request->referrer ?: ['/bench']);
	}

	/** Abort with 403 if the current user is not an admin. */
	private function requireAdmin(): void
	{
		$identity = Yii::$app->user->identity;
		if (!$identity || !$identity->is_admin) {
			throw new \yii\web\ForbiddenHttpException();
		}
	}

}