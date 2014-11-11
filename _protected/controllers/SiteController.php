<?php
namespace app\controllers;

use app\models\LoginForm;
use app\models\Setting;
use app\models\AccountActivation;
use app\models\PasswordResetRequestForm;
use app\models\ResetPasswordForm;
use app\models\SignupForm;
use app\models\ContactForm;
use yii\helpers\Html;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use Yii;

/**
 * -----------------------------------------------------------------------------
 * Site controller.
 * It is responsible for displaying static pages, logging users in and out, 
 * signup and account activation, password reset.
 * -----------------------------------------------------------------------------
 */
class SiteController extends Controller
{
    /**
     * =========================================================================
     * Returns a list of behaviors that this component should behave as. 
     * =========================================================================
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout', 'signup'],
                'rules' => [
                    [
                        'actions' => ['signup'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * ======================================================================
     * Declares external actions for the controller.
     * ======================================================================
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

//------------------------------------------------------------------------------------------------//
// STATIC PAGES
//------------------------------------------------------------------------------------------------//

    /**
     * ======================================================================
     * Displays the index (home) page. 
     * Use it in case your home page contains static content.      
     * ======================================================================
     *
     * @return mixed  index view.
     * ______________________________________________________________________
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    /**
     * ======================================================================
     * Displays the about static page.
     * ======================================================================
     *
     * @return mixed  about view.
     * ______________________________________________________________________
     */
    public function actionAbout()
    {
        return $this->render('about');
    }

    /**
     * ======================================================================
     * Displays the contact static page and sends the contact email.
     * ======================================================================
     *
     * @return mixed  contact view.
     * ______________________________________________________________________
     */
    public function actionContact()
    {
        $model = new ContactForm();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) 
        {
            if ($model->contact(Yii::$app->params['adminEmail'])) 
            {
                Yii::$app->session->setFlash('success', 
                    'Thank you for contacting us. We will respond to you as soon as possible.');
            } 
            else 
            {
                Yii::$app->session->setFlash('error', 'There was an error sending email.');
            }

            return $this->refresh();
        } 
        else 
        {
            return $this->render('contact', [
                'model' => $model,
            ]);
        }
    }

//------------------------------------------------------------------------------------------------//
// LOG IN / LOG OUT / PASSWORD RESET
//------------------------------------------------------------------------------------------------//

    /**
     * ======================================================================
     * Logs in the user if his account is activated, 
     * if not, displays appropriate message.
     * ======================================================================
     *
     * @return mixed  requested|login view.
     * ______________________________________________________________________
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) 
        {
            return $this->goHome();
        }

        // get setting value for 'Login With Email'
        $lwe = Setting::get(Setting::LOGIN_WITH_EMAIL);

        // if 'L.W.E.' value is 'YES' we instantiate LoginForm in 'lwe' scenario
        $model = $lwe ? new LoginForm(['scenario' => 'lwe']) : new LoginForm();

        // now we can try to log in the user
        if ($model->load(Yii::$app->request->post()) && $model->login()) 
        {
            return $this->goBack();
        }
        // user couldn't be logged in, because he has not activated his account
        elseif($model->notActivated())
        {
            // if his account is not activated, he will have to activate it first
            Yii::$app->session->setFlash('error', 
                'You have to activate your account first. Please check your email.');

            return $this->refresh();
        }    
        // account is activated, but some other errors have happened
        else
        {
            return $this->render('login', [
                'model' => $model,
            ]);
        }
    }

    /**
     * =========================================================================
     * Logs out the user.
     * =========================================================================
     * 
     * @return mixed  homepage view.
     * _________________________________________________________________________
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

/*----------------*
 * PASSWORD RESET *
 *----------------*/

    /**
     * =========================================================================
     * Sends email that contains link for password reset action.
     * =========================================================================
     *
     * @return mixed  homepage|requestPasswordResetToken view.
     * _________________________________________________________________________
     */
    public function actionRequestPasswordReset()
    {
        $model = new PasswordResetRequestForm();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) 
        {
            if ($model->sendEmail()) 
            {
                Yii::$app->getSession()->setFlash('success', 
                    'Check your email for further instructions.');

                return $this->goHome();
            } 
            else 
            {
                Yii::$app->getSession()->setFlash('error', 
                    'Sorry, we are unable to reset password for email provided.');
            }
        }
        else
        {
            return $this->render('requestPasswordResetToken', [
                'model' => $model,
            ]);
        }
    }

    /**
     * =========================================================================
     * Resets password.
     * =========================================================================
     *
     * @param  string $token Password reset token.
     *
     * @throws BadRequestHttpException
     *
     * @return mixed           homepage|resetPassword view
     * _________________________________________________________________________
     */
    public function actionResetPassword($token)
    {
        try 
        {
            $model = new ResetPasswordForm($token);
        } 
        catch (InvalidParamException $e) 
        {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($model->load(Yii::$app->request->post()) 
            && $model->validate() && $model->resetPassword()) 
        {
            Yii::$app->getSession()->setFlash('success', 'New password was saved.');

            return $this->goHome();
        }
        else
        {
            return $this->render('resetPassword', [
                'model' => $model,
            ]);
        }       
    }    

//------------------------------------------------------------------------------------------------//
// SIGN UP / ACCOUNT ACTIVATION
//------------------------------------------------------------------------------------------------//

    /**
     * =========================================================================
     * Signs up the user. 
     * If user need to activate his account via email, we will display him 
     * message with instructions and send him account activation email 
     * ( with link containing account activation token ). If activation is not 
     * necessary, we will log him in right after sign up process is complete. 
     * NOTE: You can decide whether or not activation is necessary,
     * see setting page as The Creator.
     * =========================================================================
     *
     * @return mixed  signup|login|home view.
     * _________________________________________________________________________
     */
    public function actionSignup()
    {  
        // get setting value for 'Registration Needs Activation'
        $rna = Setting::get(Setting::REGISTRATION_NEEDS_ACTIVATION);

        // if 'R.N.A.' value is 'YES', we instantiate SignupForm in 'rna' scenario
        $model = $rna ? new SignupForm(['scenario' => 'rna']) : new SignupForm();

        // collect and validate user data
        if ($model->load(Yii::$app->request->post()) && $model->validate())
        {
            // try to save user data in database
            if ($user = $model->signup()) 
            {
                // activation is needed, use signupWithActivation()
                if ($rna) 
                {
                    $this->signupWithActivation($model, $user);
                }
                // activation is not needed, try to login user 
                else 
                {
                    if (Yii::$app->getUser()->login($user)) 
                    {
                        return $this->goHome();
                    }
                }

                return $this->refresh();             
            }
            // user could not be saved in database
            else
            {
                // display error message to user
                Yii::$app->session->setFlash('error', 
                    "We couldn't sign you up, please contact us.");

                // log this error, so we can debug possible problem easier.
                Yii::error('Signup failed! 
                    User '.Html::encode($user->username).' could not sign up.
                    Possible causes: something strange happened while saving user in database.');

                return $this->refresh();
            }
        }
                
        return $this->render('signup', [
            'model' => $model,
        ]);     
    }

    /**
     * =========================================================================
     * Sign up user with activation.
     * User will have to activate his account using activation link that we will 
     * send him via email.
     * =========================================================================
     *
     * @param  object  $model  SignupForm.
     *
     * @param  object  $user   User.
     *
     * @return mixed
     * _________________________________________________________________________
     */
    private function signupWithActivation($model, $user)
    {
        // try to send account activation email
        if ($model->sendAccountActivationEmail($user)) 
        {
            Yii::$app->session->setFlash('success', 
                'Hello '.Html::encode($user->username).'. 
                To be able to log in, you need to confirm your registration. 
                Please check your email, we have sent you a message.');
        }
        // email could not be sent
        else 
        {
            // display error message to user
            Yii::$app->session->setFlash('error', 
                "We couldn't send you account activation email, please contact us.");

            // log this error, so we can debug possible problem easier.
            Yii::error('Signup failed! 
                User '.Html::encode($user->username).' could not sign up.
                Possible causes: verification email could not be sent.');
        }
    }

/*--------------------*
 * ACCOUNT ACTIVATION *
 *--------------------*/

    /**
     * =========================================================================
     * Activates the user account so he can log in into system.
     * =========================================================================
     *
     * @param  string $token Account activation token.
     *
     * @throws BadRequestHttpException
     *
     * @return string          login view.
     * _________________________________________________________________________
     */
    public function actionActivateAccount($token)
    {
        try 
        {
            $user = new AccountActivation($token);
        } 
        catch (InvalidParamException $e) 
        {
            throw new BadRequestHttpException($e->getMessage());
        }

        if ($user->activateAccount()) 
        {
            Yii::$app->getSession()->setFlash('success', 
                'Success! You can now log in. 
                Thank you '.Html::encode($user->username).' for joining us!');
        }
        else
        {
            Yii::$app->getSession()->setFlash('error', 
                ''.Html::encode($user->username).' your account could not be activated, 
                please contact us!');
        }

        return $this->redirect('login');
    }
}
