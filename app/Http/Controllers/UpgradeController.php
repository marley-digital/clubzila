<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Requests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Categories;
use App\Models\User;
use App\Models\Media;
use App\Models\Messages;
use App\Models\MediaMessages;
use App\Models\AdminSettings;
use App\Models\Subscriptions;
use App\Models\Updates;
use App\Models\PaymentGateways;
use App\Models\Languages;
use App\Models\Referrals;
use App\Models\ReferralTransactions;
use App\Helper;

class UpgradeController extends Controller {

	public function __construct(AdminSettings $settings, Updates $updates, User $user) {
		$this->settings  = $settings::first();
		$this->user      = $user::first();
		$this->updates   = $updates::first();
 }

 /**
	* Move a file
	*
	*/
 private static function moveFile($file, $newFile, $copy)
 {
	 if (File::exists($file) && $copy == false) {
		 	 File::delete($newFile);
			 File::move($file, $newFile);
	 } else if(File::exists($newFile) && isset($copy)) {
			 File::copy($newFile, $file);
	 }
 }

 /**
	* Copy a directory
	*
	*/
 private static function moveDirectory($directory, $destination, $copy)
 {
	 if (File::isDirectory($directory) && $copy == false) {
			 File::moveDirectory($directory, $destination);
	 } else if(File::isDirectory($destination) && isset($copy)) {
			 File::copyDirectory($destination, $directory);
	 }
 }

	public function update($version)
	{
		$DS = DIRECTORY_SEPARATOR;

		$ROOT = base_path().$DS;
		$APP = app_path().$DS;
		$BOOTSTRAP_CACHE = base_path('bootstrap'.$DS.'cache').$DS;
		$MODELS = app_path('Models').$DS;
		$NOTIFICATIONS = app_path('Notifications').$DS;
		$CONTROLLERS = app_path('Http'. $DS . 'Controllers').$DS;
		$CONTROLLERS_AUTH = app_path('Http'. $DS . 'Controllers'. $DS . 'Auth').$DS;
		$MIDDLEWARE = app_path('Http'. $DS . 'Middleware'). $DS;
		$JOBS = app_path('Jobs').$DS;
		$TRAITS = app_path('Http'. $DS . 'Controllers'. $DS . 'Traits').$DS;
		$PROVIDERS = app_path('Providers').$DS;
		$EVENTS = app_path('Events').$DS;
		$LISTENERS = app_path('Listeners').$DS;

		$CONFIG = config_path().$DS;
		$ROUTES = base_path('routes').$DS;

		$PUBLIC_JS_ADMIN = public_path('admin'.$DS.'js').$DS;
		$PUBLIC_CSS_ADMIN = public_path('admin'.$DS.'css').$DS;
		$PUBLIC_JS = public_path('js').$DS;
		$PUBLIC_CSS = public_path('css').$DS;
		$PUBLIC_IMG = public_path('img').$DS;
		$PUBLIC_IMG_ICONS = public_path('img'.$DS.'icons').$DS;
		$PUBLIC_FONTS = public_path('webfonts').$DS;

		$VIEWS = resource_path('views').$DS;
		$VIEWS_ADMIN = resource_path('views'. $DS . 'admin').$DS;
		$VIEWS_AJAX = resource_path('views'. $DS . 'ajax').$DS;
		$VIEWS_AUTH = resource_path('views'. $DS . 'auth').$DS;
		$VIEWS_AUTH_PASS = resource_path('views'. $DS . 'auth'.$DS.'passwords').$DS;
		$VIEWS_EMAILS = resource_path('views'. $DS . 'emails').$DS;
		$VIEWS_ERRORS = resource_path('views'. $DS . 'errors').$DS;
		$VIEWS_INCLUDES = resource_path('views'. $DS . 'includes').$DS;
		$VIEWS_INSTALL = resource_path('views'. $DS . 'installer').$DS;
		$VIEWS_INDEX = resource_path('views'. $DS . 'index').$DS;
		$VIEWS_LAYOUTS = resource_path('views'. $DS . 'layouts').$DS;
		$VIEWS_PAGES = resource_path('views'. $DS . 'pages').$DS;
		$VIEWS_USERS = resource_path('views'. $DS . 'users').$DS;

		$upgradeDone = '<h2 style="text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #4BBA0B;">'.trans('admin.upgrade_done').' <a style="text-decoration: none; color: #F50;" href="'.url('/').'">'.trans('error.go_home').'</a></h2>';

		if ($version == '1.1') {

			//============ Starting moving files...
			$oldVersion = $this->settings->version;
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = true;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion || !$this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

			//============== Files Affected ================//
			$file1 = 'Helper.php';
			$file2 = 'UserController.php';
			$file3 = 'StripeWebHookController.php';

			$file4 = 'Messages.php';
			$file5 = 'Comments.php';
			$file6 = 'Notifications.php';

			$file7 = 'edit_my_page.blade.php';
			$file8 = 'blog.blade.php';
			$file9 = 'posts.blade.php';
			$file10 = 'updates.blade.php';

			$file11 = 'app-functions.js';


			//============== Moving Files ================//
			$this->moveFile($path.$file1, $APP.$file1, $copy);
			$this->moveFile($path.$file2, $CONTROLLERS.$file2, $copy);
			$this->moveFile($path.$file3, $CONTROLLERS.$file3, $copy);

			$this->moveFile($path.$file4, $MODELS.$file4, $copy);
			$this->moveFile($path.$file5, $MODELS.$file5, $copy);
			$this->moveFile($path.$file6, $MODELS.$file6, $copy);

			$this->moveFile($path.$file7, $VIEWS_USERS.$file7, $copy);
			$this->moveFile($path.$file8, $VIEWS_INDEX.$file8, $copy);
			$this->moveFile($path.$file9, $VIEWS_ADMIN.$file9, $copy);
			$this->moveFile($path.$file10, $VIEWS_INCLUDES.$file10, $copy);

			$this->moveFile($path.$file11, $PUBLIC_JS.$file11, $copy);


			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->whereId(1)->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');

			return $upgradeDone;

		}
		//<<---- End Version 1.1 ----->>

		if ($version == '1.2') {

			//============ Starting moving files...
			$oldVersion = $this->settings->version;
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = true;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion || !$this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

			if (! Schema::hasColumn('admin_settings', 'widget_creators_featured', 'home_style')) {
						Schema::table('admin_settings', function($table) {
						 $table->enum('widget_creators_featured', ['on', 'off'])->default('on');
						 $table->unsignedInteger('home_style');
				});
			}

			if (! Schema::hasColumn('updates', 'fixed_post')) {
						Schema::table('updates', function($table) {
						 $table->enum('fixed_post', ['0', '1'])->default('0');
				});
			}

			if (! Schema::hasColumn('users', 'dark_mode')) {
						Schema::table('users', function($table) {
						 $table->enum('dark_mode', ['on', 'off'])->default('off');
				});
			}

			// Create Table Bookmarks
				if (! Schema::hasTable('bookmarks')) {
					Schema::create('bookmarks', function($table)
							 {
									 $table->increments('id');
									 $table->unsignedInteger('user_id')->index();
									 $table->unsignedInteger('updates_id')->index();
									 $table->timestamps();
							 });
			 }// <<--- End Create Table Bookmarks

			//============== Files Affected ================//
			$file1 = 'UpdatesController.php';
			$file2 = 'UserController.php';
			$file3 = 'AdminController.php';
			$file4 = 'HomeController.php';
			$file5 = 'MessagesController.php';
			$file6 = 'SocialAccountService.php';
			$file7 = 'PayPalController.php';

			$file8 = 'UserDelete.php'; // Traits
			$file9 = 'User.php';
			$file10 = 'Bookmarks.php';
			$file11 = 'Updates.php';

			$file12 = 'web.php';

			$file14 = 'bookmarks.blade.php';
			$file15 = 'home-session.blade.php';
			$file16 = 'css_general.blade.php';
			$file17 = 'javascript_general.blade.php';
			$file18 = 'limits.blade.php';
			$file19 = 'navbar.blade.php';
			$file20 = 'footer.blade.php';

			$file21 = 'settings.blade.php';
			$file22 = 'layout.blade.php';
			$file23 = 'updates.blade.php';

			$file24 = 'home.blade.php';
			$file25 = 'profile.blade.php';

			$file26 = 'withdrawals.blade.php';
			$file27 = 'withdrawals.blade.php';
			$file28 = 'social-login.blade.php';
			$file29 = 'app.blade.php';

			$file30 = 'app-functions.js';
			$file31 = 'bootstrap-dark.min.css';

			$file32 = 'bell-light.svg';
			$file33 = 'compass-light.svg';
			$file34 = 'home-light.svg';
			$file35 = 'paper-light.svg';


			//============== Moving Files ================//
			$this->moveFile($path.$file1, $CONTROLLERS.$file1, $copy);
			$this->moveFile($path.$file2, $CONTROLLERS.$file2, $copy);
			$this->moveFile($path.$file3, $CONTROLLERS.$file3, $copy);
			$this->moveFile($path.$file4, $CONTROLLERS.$file4, $copy);
			$this->moveFile($path.$file5, $CONTROLLERS.$file5, $copy);
			$this->moveFile($path.$file6, $APP.$file6, $copy);
			$this->moveFile($path.$file7, $CONTROLLERS.$file7, $copy);

			$this->moveFile($path.$file8, $TRAITS.$file8, $copy);
			$this->moveFile($path.$file9, $MODELS.$file9, $copy);
			$this->moveFile($path.$file10, $MODELS.$file10, $copy);
			$this->moveFile($path.$file11, $MODELS.$file11, $copy);

			$this->moveFile($path.$file12, $ROUTES.$file12, $copy);

			$this->moveFile($path.$file14, $VIEWS_USERS.$file14, $copy);
			$this->moveFile($path.$file15, $VIEWS_INDEX.$file15, $copy);
			$this->moveFile($path.$file16, $VIEWS_INCLUDES.$file16, $copy);
			$this->moveFile($path.$file17, $VIEWS_INCLUDES.$file17, $copy);
			$this->moveFile($path.$file18, $VIEWS_ADMIN.$file18, $copy);
			$this->moveFile($path.$file19, $VIEWS_INCLUDES.$file19, $copy);
			$this->moveFile($path.$file20, $VIEWS_INCLUDES.$file20, $copy);
			$this->moveFile($path.$file21, $VIEWS_ADMIN.$file21, $copy);
			$this->moveFile($path.$file22, $VIEWS_ADMIN.$file22, $copy);
			$this->moveFile($path.$file23, $VIEWS_INCLUDES.$file23, $copy);
			$this->moveFile($path.$file24, $VIEWS_INDEX.$file24, $copy);
			$this->moveFile($path.$file25, $VIEWS_USERS.$file25, $copy);
			$this->moveFile($path.$file26, $VIEWS_USERS.$file26, $copy);
			$this->moveFile($pathAdmin.$file27, $VIEWS_ADMIN.$file27, $copy);
			$this->moveFile($path.$file28, $VIEWS_ADMIN.$file28, $copy);
			$this->moveFile($path.$file29, $VIEWS_LAYOUTS.$file29, $copy);

			$this->moveFile($path.$file30, $PUBLIC_JS.$file30, $copy);
			$this->moveFile($path.$file31, $PUBLIC_CSS.$file31, $copy);

			$this->moveFile($path.$file32, $PUBLIC_IMG_ICONS.$file32, $copy);
			$this->moveFile($path.$file33, $PUBLIC_IMG_ICONS.$file33, $copy);
			$this->moveFile($path.$file34, $PUBLIC_IMG_ICONS.$file34, $copy);
			$this->moveFile($path.$file35, $PUBLIC_IMG_ICONS.$file35, $copy);


			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->whereId(1)->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');

			return $upgradeDone;

		}
		//<<---- End Version 1.2 ----->>

		if ($version == '1.3') {

			//============ Starting moving files...
			$oldVersion = $this->settings->version;
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = true;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion || !$this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

			if (! Schema::hasColumn('admin_settings', 'file_size_allowed_verify_account')) {
						Schema::table('admin_settings', function($table) {
						 $table->unsignedInteger('file_size_allowed_verify_account');
				});

				if (Schema::hasColumn('admin_settings', 'file_size_allowed_verify_account')) {
					AdminSettings::whereId(1)->update([
								'file_size_allowed_verify_account' => 1024
							]);
				}
			}

			//============== Files Affected ================//
			$file3 = 'AdminController.php';
			$file5 = 'MessagesController.php';

			$file8 = 'UserDelete.php'; // Traits

			$file14 = 'verify_account.blade.php';
			$file16 = 'css_general.blade.php';
			$file18 = 'limits.blade.php';

			$file22 = 'dashboard.blade.php';

			$file29 = 'app.blade.php';

			$file30 = 'app-functions.js';
			$file31 = 'messages.js';

			//============== Moving Files ================//
			$this->moveFile($path.$file3, $CONTROLLERS.$file3, $copy);
			$this->moveFile($path.$file5, $CONTROLLERS.$file5, $copy);

			$this->moveFile($path.$file8, $TRAITS.$file8, $copy);

			$this->moveFile($path.$file14, $VIEWS_USERS.$file14, $copy);
			$this->moveFile($path.$file16, $VIEWS_INCLUDES.$file16, $copy);
			$this->moveFile($path.$file18, $VIEWS_ADMIN.$file18, $copy);

			$this->moveFile($path.$file22, $VIEWS_ADMIN.$file22, $copy);

			$this->moveFile($path.$file29, $VIEWS_LAYOUTS.$file29, $copy);

			$this->moveFile($path.$file30, $PUBLIC_JS.$file30, $copy);
			$this->moveFile($path.$file31, $PUBLIC_JS.$file31, $copy);


			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->whereId(1)->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');

			return $upgradeDone;

		}
		//<<---- End Version 1.3 ----->>

		if ($version == '1.4') {

			//============ Starting moving files...
			$oldVersion = $this->settings->version;
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = true;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion || !$this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

			PaymentGateways::whereId(1)->update([
						'recurrent' => 'no',
						'logo' => 'paypal.png',
					]);

					PaymentGateways::whereId(2)->update([
								'logo' => 'stripe.png',
							]);

			//============== Files Affected ================//
			$file3 = 'AdminController.php';
			$file5 = 'UserController.php';
			$file18 = 'storage.blade.php';
			$file29 = 'app.blade.php';


			//============== Moving Files ================//
			$this->moveFile($path.$file3, $CONTROLLERS.$file3, $copy);
			$this->moveFile($path.$file5, $CONTROLLERS.$file5, $copy);
			$this->moveFile($path.$file18, $VIEWS_ADMIN.$file18, $copy);
			$this->moveFile($path.$file29, $VIEWS_LAYOUTS.$file29, $copy);

			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->whereId(1)->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');

			return $upgradeDone;

		}
		//<<---- End Version 1.4 ----->>

		if ($version == '1.5') {

			//============ Starting moving files...
			$oldVersion = $this->settings->version;
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = true;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion || !$this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

			//============== Files Affected ================//
			$file5 = 'UserController.php';
			$file6 = 'SocialAccountService.php';
			$file18 = 'updates.blade.php';
			$file29 = 'app.blade.php';
			$file30 = 'profile.blade.php';
			$file31 = 'edit_my_page.blade.php';


			//============== Moving Files ================//
			$this->moveFile($path.$file5, $CONTROLLERS.$file5, $copy);
			$this->moveFile($path.$file6, $APP.$file6, $copy);
			$this->moveFile($path.$file18, $VIEWS_INCLUDES.$file18, $copy);
			$this->moveFile($path.$file29, $VIEWS_LAYOUTS.$file29, $copy);
			$this->moveFile($path.$file30, $VIEWS_USERS.$file30, $copy);
			$this->moveFile($path.$file31, $VIEWS_USERS.$file31, $copy);

			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->whereId(1)->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');

			return $upgradeDone;

		}
		//<<---- End Version 1.5 ----->>

		if ($version == '1.6') {

			//============ Starting moving files...
			$oldVersion = $this->settings->version;
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = true;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion || !$this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

			if (! Schema::hasColumn('users',
					'gender',
					'birthdate',
					'allow_download_files',
					'language'
				)) {
						Schema::table('users', function($table) {
							$table->string('gender', 50);
 						 	$table->string('birthdate', 30);
						  $table->enum('allow_download_files', ['no', 'yes'])->default('no');
							$table->string('language', 10);
				});
			}

			if (! Schema::hasColumn('transactions', 'type')) {
						Schema::table('transactions', function($table) {
						 $table->enum('type', ['subscription', 'tip', 'ppv'])->default('subscription');
				});
			}

			if (! Schema::hasColumn('admin_settings',
					'payout_method_paypal',
					 'payout_method_bank',
					 'min_tip_amount',
					 'max_tip_amount',
					 'min_ppv_amount',
					 'max_ppv_amount',
					 'min_deposits_amount',
					 'max_deposits_amount',
					 'button_style',
					 'twitter_login',
					 'hide_admin_profile',
					 'requests_verify_account',
					 'navbar_background_color',
					 'navbar_text_color',
					 'footer_background_color',
					 'footer_text_color'

					 )
					) {
						Schema::table('admin_settings', function($table) {
						 $table->enum('payout_method_paypal', ['on', 'off'])->default('on');
						 $table->enum('payout_method_bank', ['on', 'off'])->default('on');
						 $table->unsignedInteger('min_tip_amount');
						 $table->unsignedInteger('max_tip_amount');
						 $table->unsignedInteger('min_ppv_amount');
						 $table->unsignedInteger('max_ppv_amount');
						 $table->unsignedInteger('min_deposits_amount');
						 $table->unsignedInteger('max_deposits_amount');
						 $table->enum('button_style', ['rounded', 'normal'])->default('rounded');
						 $table->enum('twitter_login', ['on', 'off'])->default('off');
						 $table->enum('hide_admin_profile', ['on', 'off'])->default('off');
						 $table->enum('requests_verify_account', ['on', 'off'])->default('on');
						 $table->string('navbar_background_color', 30);
						 $table->string('navbar_text_color', 30);
						 $table->string('footer_background_color', 30);
						 $table->string('footer_text_color', 30);

				});
			}

			file_put_contents(
					'.env',
					"\nTWITTER_CLIENT_ID=\nTWITTER_CLIENT_SECRET=\n",
					FILE_APPEND
			);

			$sql = new Languages();
			$sql->name = 'Español';
			$sql->abbreviation = 'es';
			$sql->save();

			AdminSettings::whereId(1)->update([
						'navbar_background_color' => '#ffffff',
						'navbar_text_color' => '#3a3a3a',
						'footer_background_color' => '#ffffff',
						'footer_text_color' => '#5f5f5f',
						'min_tip_amount' => 5,
						'max_tip_amount' => 99
					]);

			DB::statement("ALTER TABLE reports MODIFY reason ENUM('copyright', 'privacy_issue', 'violent_sexual', 'spoofing', 'spam', 'fraud', 'under_age') NOT NULL");

			// Update Version
		 $this->settings->whereId(1)->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');

			return $upgradeDone;

		}
		//<<---- End Version 1.6 ----->>

		if ($version == '1.7') {

			//============ Starting moving files...
			$oldVersion = $this->settings->version;
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = true;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion || ! $this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

			//============== Files Affected ================//
			$file5 = 'UserController.php';
			$file6 = 'RegisterController.php';
			$file18 = 'home-login.blade.php';
			$file29 = 'app.blade.php';
			$file30 = 'password.blade.php';
			$file31 = 'edit_my_page.blade.php';
			$file32 = 'invoice.blade.php';


			//============== Moving Files ================//
			$this->moveFile($path.$file5, $CONTROLLERS.$file5, $copy);
			$this->moveFile($path.$file6, $CONTROLLERS_AUTH.$file6, $copy);
			$this->moveFile($path.$file18, $VIEWS_INDEX.$file18, $copy);
			$this->moveFile($path.$file29, $VIEWS_LAYOUTS.$file29, $copy);
			$this->moveFile($path.$file30, $VIEWS_USERS.$file30, $copy);
			$this->moveFile($path.$file31, $VIEWS_USERS.$file31, $copy);
			$this->moveFile($path.$file32, $VIEWS_USERS.$file32, $copy);

			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->whereId(1)->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');

			return $upgradeDone;

		}
		//<<---- End Version 1.7 ----->>

		if ($version == '1.8') {

			//============ Starting moving files...
			$oldVersion = '1.6';
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = false;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion && $this->settings->version != '1.7' || ! $this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

			if (! Schema::hasColumn('payment_gateways', 'subscription')) {
						Schema::table('payment_gateways', function($table) {
						 $table->enum('subscription', ['yes', 'no'])->default('yes');
				});
			}

			DB::table('payment_gateways')->insert([
				[
					'name' => 'Bank Transfer',
					'type' => 'bank',
					'enabled' => '0',
					'fee' => 0.0,
					'fee_cents' => 0.00,
					'email' => '',
					'key' => '',
					'key_secret' => '',
					'bank_info' => '',
					'recurrent' => 'no',
					'logo' => '',
					'webhook_secret' => '',
					'subscription' => 'no',
					'token' => str_random(150),
			]
	]);

		if (! Schema::hasColumn('admin_settings', 'announcements', 'preloading', 'preloading_image', 'watermark')) {
						Schema::table('admin_settings', function($table) {
						 $table->text('announcements');
						 $table->enum('preloading', ['on', 'off'])->default('off');
						 $table->string('preloading_image', 100);
						 $table->enum('watermark', ['on', 'off'])->default('on');
						 $table->enum('earnings_simulator', ['on', 'off'])->default('on');
				});
			}

			if (! Schema::hasColumn('users', 'free_subscription', 'wallet')) {
						Schema::table('users', function($table) {
						 $table->enum('free_subscription', ['yes', 'no'])->default('no');
						 $table->decimal('wallet', 10, 2);
						 $table->string('tiktok', 200);
						 $table->string('snapchat', 200);
				});
			}

			if (! Schema::hasColumn('updates', 'price', 'youtube', 'vimeo', 'file_name', 'file_size')) {
						Schema::table('updates', function($table) {
						 $table->decimal('price', 10, 2);
						 $table->string('video_embed', 200);
						 $table->string('file_name', 255);
						 $table->string('file_size', 50);
				});
			}

			if (! Schema::hasColumn('subscriptions', 'free')) {
						Schema::table('subscriptions', function($table) {
						 $table->enum('free', ['yes', 'no'])->default('no');
				});
			}

			if (! Schema::hasColumn('messages', 'price', 'tip', 'tip_amount')) {
						Schema::table('messages', function($table) {
						 $table->decimal('price', 10, 2);
						 $table->enum('tip', ['yes', 'no'])->default('no');
						 $table->unsignedInteger('tip_amount');
				});
			}

			// Create table Deposits
			if (! Schema::hasTable('deposits')) {

					Schema::create('deposits', function ($table) {

					$table->engine = 'InnoDB';
					$table->increments('id');
					$table->unsignedInteger('user_id');
					$table->string('txn_id', 200);
					$table->unsignedInteger('amount');
					$table->string('payment_gateway', 100);
					$table->timestamp('date');
					$table->enum('status', ['active', 'pending'])->default('active');
					$table->string('screenshot_transfer', 100);
			});
		}// <<< --- Create table Deposits

			//============== Files Affected ================//
			$files = [
				'UpdatesController.php' => $CONTROLLERS,
				'PayPalController.php' => $CONTROLLERS,
				'AdminController.php' => $CONTROLLERS,
				'HomeController.php' => $CONTROLLERS,
				'MessagesController.php' => $CONTROLLERS,
				'SubscriptionsController.php' => $CONTROLLERS,
				'StripeController.php' => $CONTROLLERS,
				'AddFundsController.php' => $CONTROLLERS,
				'UserController.php' => $CONTROLLERS,
				'InstallScriptController.php' => $CONTROLLERS,
				'Helper.php' => $APP,
				'Subscriptions.php' => $MODELS,
				'Deposits.php' => $MODELS,
				'app.blade.php' => $VIEWS_LAYOUTS,
				'javascript_general.blade.php' => $VIEWS_INCLUDES,
				'home-login.blade.php' => $VIEWS_INDEX,
				'register.blade.php' => $VIEWS_AUTH,
				'notifications.blade.php' => $VIEWS_USERS,
				'my_payments.blade.php' => $VIEWS_USERS,
				'navbar.blade.php' => $VIEWS_INCLUDES,
				'edit-update.blade.php' => $VIEWS_USERS,
				'listing-creators.blade.php' => $VIEWS_INCLUDES,
				'explore_creators.blade.php' => $VIEWS_INCLUDES,
				'listing-explore-creators.blade.php' => $VIEWS_INCLUDES,
				'updates.blade.php' => $VIEWS_INCLUDES,
				'footer-tiny.blade.php' => $VIEWS_INCLUDES,
				'messages-chat.blade.php' => $VIEWS_INCLUDES,
				'footer.blade.php' => $VIEWS_INCLUDES,
				'profile.blade.php' => $VIEWS_USERS,
				'cards-settings.blade.php' => $VIEWS_INCLUDES,
				'subscription.blade.php' => $VIEWS_USERS,
				'messages-inbox.blade.php' => $VIEWS_INCLUDES,
				'css_general.blade.php' => $VIEWS_INCLUDES,
				'invoice.blade.php' => $VIEWS_USERS,
				'my_subscriptions.blade.php' => $VIEWS_USERS,
				'my_subscribers.blade.php' => $VIEWS_USERS,
				'dashboard.blade.php' => $VIEWS_USERS,
				'listing-categories.blade.php' => $VIEWS_INCLUDES,
				'email.blade.php' => $VIEWS_AUTH_PASS,
				'payout_method.blade.php' => $VIEWS_USERS,
				'sitemaps.blade.php' => $VIEWS_INDEX,
				'home-session.blade.php' => $VIEWS_INDEX,
				'form-post.blade.php' => $VIEWS_INCLUDES,
				'edit_my_page.blade.php' => $VIEWS_USERS,
				'home.blade.php' => $VIEWS_INDEX,
				'wallet.blade.php' => $VIEWS_USERS,
				'withdrawals.blade.php' => $VIEWS_USERS,
				'messages-show.blade.php' => $VIEWS_USERS,
				'requirements.blade.php' => $VIEWS_INSTALL,
				'transfer_verification.blade.php' => $VIEWS_EMAILS,
				'verify_account' => $VIEWS_USERS,
				'web.php' => $ROUTES,
				'arial.TTF' => $PUBLIC_FONTS,
				'add-funds.js' => $PUBLIC_JS,
				'app-functions.js' => $PUBLIC_JS,
				'messages.js' => $PUBLIC_JS,
				'payment.js' => $PUBLIC_JS
			];

			$filesAdmin = [
				'verification.blade.php' => $VIEWS_ADMIN,
				'transactions.blade.php' => $VIEWS_ADMIN,
				'posts.blade.php' => $VIEWS_ADMIN,
				'deposits-view.blade.php' => $VIEWS_ADMIN,
				'dashboard.blade.php' => $VIEWS_ADMIN,
				'charts.blade.php' => $VIEWS_ADMIN,
				'deposits.blade.php' => $VIEWS_ADMIN,
				'members.blade.php' => $VIEWS_ADMIN,
				'bank-transfer-settings.blade.php' => $VIEWS_ADMIN,
				'layout.blade.php' => $VIEWS_ADMIN,
				'settings.blade.php' => $VIEWS_ADMIN,
				'payments-settings.blade.php' => $VIEWS_ADMIN
			];

			// Files
			foreach ($files as $file => $root) {
				 $this->moveFile($path.$file, $root.$file, $copy);
			}

			// Files Admin
			foreach ($filesAdmin as $file => $root) {
				 $this->moveFile($pathAdmin.$file, $root.$file, $copy);
			}

			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->whereId(1)->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');

			return $upgradeDone;

		}
		//<<---- End Version 1.8 ----->>

		if ($version == '1.9') {

			//============ Starting moving files...
			$oldVersion = '1.8';
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = true;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion  || ! $this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

			// Replace String
			$findStringLang = ');';

			// Ennglish
			$replaceLangEN    = "
	// Version 1.9
	'login_as_user' => 'Login as user',
	'login_as_user_warning' => 'This action will close your current session',
	'become_creator' => 'Become a creator',
);";
			$fileLangEN = 'resources/lang/en/general.php';
			@file_put_contents($fileLangEN, str_replace($findStringLang, $replaceLangEN, file_get_contents($fileLangEN)));

		// Español
		$replaceLangES    = "
	//----- Version 1.9
	'login_as_user' => 'Iniciar sesión como usuario',
	'login_as_user_warning' => 'Esta acción cerrará su sesión actual',
	'become_creator' => 'Conviértete en un creador',
);";
		$fileLangES = 'resources/lang/es/general.php';
		@file_put_contents($fileLangES, str_replace($findStringLang, $replaceLangES, file_get_contents($fileLangES)));

			//============== Files Affected ================//
			$files = [
				'TipController.php' => $CONTROLLERS,
				'UpdatesController.php' => $CONTROLLERS,
				'AdminController.php' => $CONTROLLERS,
				'HomeController.php' => $CONTROLLERS,
				'MessagesController.php' => $CONTROLLERS,
				'UserController.php' => $CONTROLLERS,
				'app.blade.php' => $VIEWS_LAYOUTS,
				'javascript_general.blade.php' => $VIEWS_INCLUDES,
				'navbar.blade.php' => $VIEWS_INCLUDES,
				'listing-creators.blade.php' => $VIEWS_INCLUDES,
				'listing-explore-creators.blade.php' => $VIEWS_INCLUDES,
				'updates.blade.php' => $VIEWS_INCLUDES,
				'profile.blade.php' => $VIEWS_USERS,
				'cards-settings.blade.php' => $VIEWS_INCLUDES,
				'css_general.blade.php' => $VIEWS_INCLUDES,
				'edit_my_page.blade.php' => $VIEWS_USERS,
				'home.blade.php' => $VIEWS_INDEX,
				'messages-show.blade.php' => $VIEWS_USERS,
				'web.php' => $ROUTES,
				'app-functions.js' => $PUBLIC_JS,
				'messages.js' => $PUBLIC_JS,
				'UserDelete.php' => $TRAITS,
				'functions.js' => $PUBLIC_JS_ADMIN
			];

			$filesAdmin = [
				'charts.blade.php' => $VIEWS_ADMIN,
				'deposits.blade.php' => $VIEWS_ADMIN,
				'edit-member.blade.php' => $VIEWS_ADMIN,
				'layout.blade.php' => $VIEWS_ADMIN,
				'reports.blade.php' => $VIEWS_ADMIN
			];

			// Files
			foreach ($files as $file => $root) {
				 $this->moveFile($path.$file, $root.$file, $copy);
			}

			// Files Admin
			foreach ($filesAdmin as $file => $root) {
				 $this->moveFile($pathAdmin.$file, $root.$file, $copy);
			}

			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->whereId(1)->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');

			return $upgradeDone;

		}
		//<<---- End Version 1.9 ----->>

		if ($version == '2.0') {

			//============ Starting moving files...
			$oldVersion = '1.9';
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = true;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion  || ! $this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

			file_put_contents(
					'.env',
					"\nBACKBLAZE_ACCOUNT_ID=\nBACKBLAZE_APP_KEY=\nBACKBLAZE_BUCKET=\nBACKBLAZE_BUCKET_ID=\n\nVULTR_ACCESS_KEY=\nVULTR_SECRET_KEY=\nVULTR_REGION=\nVULTR_BUCKET=\nVULTR_ENDPOINT=https://ewr1.vultrobjects.com\n\nPWA_SHORT_NAME=\"Sponzy\"\nPWA_ICON_72=public/images/icons/icon-72x72.png\nPWA_ICON_96=public/images/icons/icon-96x96.png\nPWA_ICON_128=public/images/icons/icon-128x128.png\nPWA_ICON_144=public/images/icons/icon-144x144.png\nPWA_ICON_152=public/images/icons/icon-152x152.png\nPWA_ICON_384=public/images/icons/icon-384x384.png\nPWA_ICON_512=public/images/icons/icon-512x512.png\n\nPWA_SPLASH_640=public/images/icons/splash-640x1136.png\nPWA_SPLASH_750=public/images/icons/splash-750x1334.png\nPWA_SPLASH_1125=public/images/icons/splash-1125x2436.png\nPWA_SPLASH_1242=public/images/icons/splash-1242x2208.png\nPWA_SPLASH_1536=public/images/icons/splash-1536x2048.png\nPWA_SPLASH_1668=public/images/icons/splash-1668x2224.png\nPWA_SPLASH_2048=public/images/icons/splash-2048x2732.png\n",
					FILE_APPEND
			);

			if (! Schema::hasColumn('verification_requests', 'form_w9')) {
							Schema::table('verification_requests', function($table) {
							 $table->string('form_w9', 100);
					});
				}

			if (! Schema::hasColumn('reserved', 'offline')) {
					\DB::table('reserved')->insert(
						['name' => 'offline']
					);
				}

			if (! Schema::hasColumn('admin_settings', 'custom_css', 'custom_js', 'alert_adult')) {
							Schema::table('admin_settings', function($table) {
							 $table->text('custom_css');
							 $table->text('custom_js');
							 $table->enum('alert_adult', ['on', 'off'])->default('off');
					});
				}

			if (Schema::hasTable('payment_gateways')) {
					\DB::table('payment_gateways')->insert([
						[
							'name' => 'CCBill',
							'type' => 'card',
							'enabled' => '0',
							'fee' => 0.0,
							'fee_cents' => 0.00,
							'email' => '',
							'key' => '',
							'key_secret' => '',
							'logo' => '',
							'bank_info' => '',
							'token' => str_random(150),
					],
					[
						'name' => 'Paystack',
						'type' => 'card',
						'enabled' => '0',
						'fee' => 0.0,
						'fee_cents' => 0.00,
						'email' => '',
						'key' => '',
						'key_secret' => '',
						'logo' => '',
						'bank_info' => '',
						'token' => str_random(150),
				]
					]
			);
		}

		if (! Schema::hasColumn('payment_gateways', 'ccbill_accnum', 'ccbill_subacc', 'ccbill_flexid', 'ccbill_salt')) {
					Schema::table('payment_gateways', function($table) {
					 $table->string('ccbill_accnum', 200);
					 $table->string('ccbill_subacc', 200);
					 $table->string('ccbill_flexid', 200);
					 $table->string('ccbill_salt', 200);
			});
		}

			PaymentGateways::whereId(1)->update([
						'recurrent' => 'yes'
					]);

			if (! Schema::hasColumn('users',
					'paystack_plan',
					'paystack_authorization_code',
					'paystack_last4',
					'paystack_exp',
					'paystack_card_brand'
				)) {
						Schema::table('users', function($table) {
						 $table->string('paystack_plan', 100);
						 $table->string('paystack_authorization_code', 100);
						 $table->unsignedInteger('paystack_last4');
						 $table->string('paystack_exp', 50);
						 $table->string('paystack_card_brand', 25);
				});
			}

		if (! Schema::hasColumn('subscriptions', 'subscription_id', 'cancelled')) {
						Schema::table('subscriptions', function($table) {
						 $table->string('subscription_id', 50);
						 $table->enum('cancelled', ['yes', 'no'])->default('no');
				});
			}


			// Replace String
			$findStringLang = ');';

			// Ennglish
			$replaceLangEN    = "
		//----- Version 2.0
		'show_errors' => 'Show Errors',
		'info_show_errors' => 'Recommended only in local or test mode',
		'alert_not_subscription' => 'You must set a price or enable Free Subscription to activate your subscription',
		'activate' => 'Activate',
		'my_cards' => 'My cards',
		'info_my_cards' => 'Cards available in your account',
		'add' => 'Add',
		'expiry' => 'Expiry',
		'powered_by' => 'Powered by',
		'notice_charge_to_card' => 'We will make a one-time charge of :amount when adding your payment card', // Not remove :amount
		'redirected_to_paypal_website' => 'You will be redirected to the PayPal website',
		'subscription_expire' => 'Your subscription will be active until',
		'subscribed_until' => 'Subscribed until',
		'cancel_subscription_paypal' => 'Cancel your subscription from your PayPal account, it will be active until',
		'confirm_cancel_payment' => 'Are you sure you want to cancel this transaction?',
		'test_smtp' => 'If you are using SMTP, do a test on the following link to verify that your data is correct.',
		'alert_paypal_delay' => '(Important: PayPal may have a delay, reload the page or wait a minute, otherwise, contact us)',
		'error_currency' => 'Currency not supported (Only NGN, USD, ZAR or GHS allowed)',
		'custom_css_js' => 'Custom CSS/JS',
		'custom_css' => 'Custom CSS (without <style> tags)',
		'custom_js' => 'Custom JavaScript (without <script> tags)',
		'show_alert_adult' => 'Show alert that the site has adult content',
		'alert_content_adult' => 'Attention! This site contains adult content, by accessing you acknowledge that you are 18 years of age.',
		'i_am_age' => 'I am of age',
		'leave' => 'Leave',
		'pwa_short_name' => 'App short name (Ex: Sponzy)',
		'alert_pwa_https' => 'You must use HTTPS (SSL) for PWA to work.',
		'error_internet_disconnected_pwa' => 'You are currently not connected to any networks.',
		'error_internet_disconnected_pwa_2' => 'Check your connection and try again',
		'complete_profile_alert' => 'To submit a verification request you must complete your profile.',
		'set_avatar' => 'Upload a profile picture',
		'set_cover' => 'Upload a cover image',
		'set_country' => 'Select your country of origin',
		'set_birthdate' => 'Set your date of birth',
		'form_w9' => 'Form W-9',
		'not_applicable' => 'Not applicable',
		'form_w9_required' => 'As a US citizen, you must submit the Form W-9',
		'upload_form_w9' => 'Upload Form W-9',
		'formats_available_verification_form_w9' => 'Invalid format, only :formats are allowed.', // Not remove/edit :formats
);";
			$fileLangEN = 'resources/lang/en/general.php';
			@file_put_contents($fileLangEN, str_replace($findStringLang, $replaceLangEN, file_get_contents($fileLangEN)));

		// Español
		$replaceLangES    = "
	//----- Version 2.0
	'show_errors' => 'Mostrar Errores',
	'info_show_errors' => 'Se recomienda solo en modo local o prueba',
	'alert_not_subscription' => 'Debe establecer un precio o habilitar la Suscripción Gratuita para activar su suscripción',
	'activate' => 'Activar',
	'my_cards' => 'Mis tarjetas',
	'info_my_cards' => 'Tarjetas disponibles en tu cuenta',
	'add' => 'Agregar',
	'expiry' => 'Vencimiento',
	'powered_by' => 'Desarrollado por',
	'notice_charge_to_card' => 'Haremos un cargo único de :amount al agregar su tarjeta de pago', // Not remove :amount
	'redirected_to_paypal_website' => 'Serás redirigido al sitio web de PayPal',
	'subscription_expire' => 'Su suscripción estará activa hasta',
	'subscribed_until' => 'Suscrito hasta',
	'cancel_subscription_paypal' => 'Cancela tu suscripción desde tu cuenta PayPal, estará activa hasta',
	'confirm_cancel_payment' => '¿Estás seguro de que desea cancelar esta transacción?',
	'test_smtp' => 'Si está usando SMTP, haz una prueba en el siguiente enlace para verificar que tus datos sean correctos.',
	'alert_paypal_delay' => '(Importante: PayPal puede tener un retraso, recargue la página o espere un minuto, de lo contrario, contáctenos)',
	'error_currency' => 'Moneda no soportada (Solo se permite NGN, USD, ZAR o GHS)',
	'custom_css_js' => 'CSS/JS Personalizado',
	'custom_css' => 'CSS Personalizado (sin la etiqueta <style>)',
	'custom_js' => 'JavaScript Personalizado (sin la etiqueta <script>)',
	'show_alert_adult' => 'Mostrar alerta que el sitio tiene contenido para adultos',
	'alert_content_adult' => '¡Atención! este sitio contiene contenido para adultos, al acceder usted admite tener 18 años de edad.',
	'i_am_age' => 'Soy mayor de edad',
	'leave' => 'Salir',
	'pwa_short_name' => 'Nombre corto de App (Ej: Sponzy)',
	'alert_pwa_https' => 'Debes usar HTTPS (SSL) para que PWA funcione.',
	'error_internet_disconnected_pwa' => 'Actualmente no estás conectado a ninguna red.',
	'error_internet_disconnected_pwa_2' => 'Verifica tu conexión e intente de nuevo',
	'complete_profile_alert' => 'Para enviar una solicitud de verificación, debe completar su perfil.',
	'set_avatar' => 'Sube una imagen de perfil',
	'set_cover' => 'Sube una imagen de portada',
	'set_country' => 'Selecciona tu país de origen',
	'set_birthdate' => 'Establece tu fecha de nacimiento',
	'form_w9' => 'Formulario W-9',
	'not_applicable' => 'No aplica',
	'form_w9_required' => 'Como ciudadano estadounidense, debe enviar el Formulario W-9',
	'upload_form_w9' => 'Subir Formulario W-9',
	'formats_available_verification_form_w9' => 'Formato no válido, solo se permiten :formats', // Not remove/edit :formats
);";
		$fileLangES = 'resources/lang/es/general.php';
		@file_put_contents($fileLangES, str_replace($findStringLang, $replaceLangES, file_get_contents($fileLangES)));

		//============== Files Affected ================//
		$files = [
			'UpdatesController.php' => $CONTROLLERS,
			'PayPalController.php' => $CONTROLLERS,
			'AdminController.php' => $CONTROLLERS,
			'HomeController.php' => $CONTROLLERS,
			'MessagesController.php' => $CONTROLLERS,
			'PaystackController.php' => $CONTROLLERS,
			'SubscriptionsController.php' => $CONTROLLERS,
			'StripeController.php' => $CONTROLLERS,
			'CommentsController.php' => $CONTROLLERS,
			'LoginController.php' => $CONTROLLERS_AUTH,
			'RegisterController.php' => $CONTROLLERS_AUTH,
			'BlogController.php' => $CONTROLLERS,
			'AddFundsController.php' => $CONTROLLERS,
			'CCBillController.php' => $CONTROLLERS,
			'UserController.php' => $CONTROLLERS,
			'TipController.php' => $CONTROLLERS,
			'Helper.php' => $APP,
			'Subscriptions.php' => $MODELS,
			'User.php' => $MODELS,
			'app.blade.php' => $VIEWS_LAYOUTS,
			'javascript_general.blade.php' => $VIEWS_INCLUDES,
			'home-login.blade.php' => $VIEWS_INDEX,
			'register.blade.php' => $VIEWS_AUTH,
			'login.blade.php' => $VIEWS_AUTH,
			'notifications.blade.php' => $VIEWS_USERS,
			'my_payments.blade.php' => $VIEWS_USERS,
			'navbar.blade.php' => $VIEWS_INCLUDES,
			'listing-creators.blade.php' => $VIEWS_INCLUDES,
			'explore_creators.blade.php' => $VIEWS_INCLUDES,
			'listing-explore-creators.blade.php' => $VIEWS_INCLUDES,
			'updates.blade.php' => $VIEWS_INCLUDES,
			'comments.blade.php' => $VIEWS_INCLUDES,
			'footer-tiny.blade.php' => $VIEWS_INCLUDES,
			'messages-chat.blade.php' => $VIEWS_INCLUDES,
			'footer.blade.php' => $VIEWS_INCLUDES,
			'profile.blade.php' => $VIEWS_USERS,
			'cards-settings.blade.php' => $VIEWS_INCLUDES,
			'subscription.blade.php' => $VIEWS_USERS,
			'messages-inbox.blade.php' => $VIEWS_INCLUDES,
			'css_general.blade.php' => $VIEWS_INCLUDES,
			'my_subscriptions.blade.php' => $VIEWS_USERS,
			'my_cards.blade.php' => $VIEWS_USERS,
			'my_subscribers.blade.php' => $VIEWS_USERS,
			'dashboard.blade.php' => $VIEWS_USERS,
			'listing-categories.blade.php' => $VIEWS_INCLUDES,
			'payout_method.blade.php' => $VIEWS_USERS,
			'home-session.blade.php' => $VIEWS_INDEX,
			'edit_my_page.blade.php' => $VIEWS_USERS,
			'home.blade.php' => $VIEWS_INDEX,
			'wallet.blade.php' => $VIEWS_USERS,
			'withdrawals.blade.php' => $VIEWS_USERS,
			'messages-show.blade.php' => $VIEWS_USERS,
			'verify_account.blade.php' => $VIEWS_USERS,
			'menu-mobile.blade.php' => $VIEWS_INCLUDES,
			'password.blade.php' => $VIEWS_USERS,
			'web.php' => $ROUTES,
			'add-funds.js' => $PUBLIC_JS,
			'serviceworker.js' => $ROOT,
			'app-functions.js' => $PUBLIC_JS,
			'messages.js' => $PUBLIC_JS,
			'core.min.js' => $PUBLIC_JS,
			'payment.js' => $PUBLIC_JS,
			'UserDelete.php' => $TRAITS,
			'Functions.php' => $TRAITS,
			'laravelpwa.php' => $CONFIG,
			'filesystems.php' => $CONFIG,
			'packages.php' => $BOOTSTRAP_CACHE,
			'verify.blade.php' => $VIEWS_EMAILS,
			'VerifyCsrfToken.php' => $MIDDLEWARE,
			'jquery.tagsinput.min.css' => public_path('plugins'.$DS.'tagsinput').$DS
		];

		$filesAdmin = [
			'verification.blade.php' => $VIEWS_ADMIN,
			'css-js.blade.php' => $VIEWS_ADMIN,
			'email-settings.blade.php' => $VIEWS_ADMIN,
			'limits.blade.php' => $VIEWS_ADMIN,
			'transactions.blade.php' => $VIEWS_ADMIN,
			'storage.blade.php' => $VIEWS_ADMIN,
			'deposits-view.blade.php' => $VIEWS_ADMIN,
			'dashboard.blade.php' => $VIEWS_ADMIN,
			'pwa.blade.php' => $VIEWS_ADMIN,
			'deposits.blade.php' => $VIEWS_ADMIN,
			'edit-member.blade.php' => $VIEWS_ADMIN,
			'members.blade.php' => $VIEWS_ADMIN,
			'bank-transfer-settings.blade.php' => $VIEWS_ADMIN,
			'paystack-settings.blade.php' => $VIEWS_ADMIN,
			'ccbill-settings.blade.php' => $VIEWS_ADMIN,
			'layout.blade.php' => $VIEWS_ADMIN,
			'settings.blade.php' => $VIEWS_ADMIN,
			'subscriptions.blade.php' => $VIEWS_ADMIN,
			'payments-settings.blade.php' => $VIEWS_ADMIN,
			'reports.blade.php' => $VIEWS_ADMIN
		];

			// Files
			foreach ($files as $file => $root) {
				 $this->moveFile($path.$file, $root.$file, $copy);
			}

			// Files Admin
			foreach ($filesAdmin as $file => $root) {
				 $this->moveFile($pathAdmin.$file, $root.$file, $copy);
			}

			// Copy Folders
			$filePathPublic1 = $path.'images';
			$pathPublic1 = public_path('images');

			$this->moveDirectory($filePathPublic1, $pathPublic1, $copy);

			// Copy Folders
			$filePathPublic2 = $path.'laravelpwa';
			$pathPublic2 = resource_path('views'.$DS.'vendor'.$DS.'laravelpwa');

			$this->moveDirectory($filePathPublic2, $pathPublic2, $copy);

			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->whereId(1)->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');

			return $upgradeDone;

		}
		//<<---- End Version 2.0 ----->>

		if ($version == '2.1') {

			//============ Starting moving files...
			$oldVersion = '2.0';
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = true;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion  || ! $this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

		//============== Files Affected ================//
		$files = [
			'UpdatesController.php' => $CONTROLLERS,
			'AdminController.php' => $CONTROLLERS,
			'HomeController.php' => $CONTROLLERS,
			'MessagesController.php' => $CONTROLLERS,
			'PaystackController.php' => $CONTROLLERS,
			'SubscriptionsController.php' => $CONTROLLERS,
			'StripeController.php' => $CONTROLLERS,
			'CommentsController.php' => $CONTROLLERS,
			'StripeWebHookController.php' => $CONTROLLERS,
			'AddFundsController.php' => $CONTROLLERS,
			'CCBillController.php' => $CONTROLLERS,
			'UserController.php' => $CONTROLLERS,
			'TipController.php' => $CONTROLLERS,
			'Helper.php' => $APP,
			'app.blade.php' => $VIEWS_LAYOUTS,
			'notifications.blade.php' => $VIEWS_USERS,
			'navbar.blade.php' => $VIEWS_INCLUDES,
			'listing-creators.blade.php' => $VIEWS_INCLUDES,
			'listing-explore-creators.blade.php' => $VIEWS_INCLUDES,
			'updates.blade.php' => $VIEWS_INCLUDES,
			'comments.blade.php' => $VIEWS_INCLUDES,
			'messages-chat.blade.php' => $VIEWS_INCLUDES,
			'footer.blade.php' => $VIEWS_INCLUDES,
			'profile.blade.php' => $VIEWS_USERS,
			'post-detail.blade.php' => $VIEWS_USERS,
			'edit-update.blade.php' =>  $VIEWS_USERS,
			'messages-inbox.blade.php' => $VIEWS_INCLUDES,
			'my_subscriptions.blade.php' => $VIEWS_USERS,
			'my_subscribers.blade.php' => $VIEWS_USERS,
			'wallet.blade.php' => $VIEWS_USERS,
			'messages-show.blade.php' => $VIEWS_USERS,
			'add-funds.js' => $PUBLIC_JS,
			'payment.js' => $PUBLIC_JS,

		];

		$filesAdmin = [
			'verification.blade.php' => $VIEWS_ADMIN,
			'dashboard.blade.php' => $VIEWS_ADMIN,
			'edit-member.blade.php' => $VIEWS_ADMIN,
			'members.blade.php' => $VIEWS_ADMIN,
			'layout.blade.php' => $VIEWS_ADMIN,
		];

			// Files
			foreach ($files as $file => $root) {
				 $this->moveFile($path.$file, $root.$file, $copy);
			}

			// Files Admin
			foreach ($filesAdmin as $file => $root) {
				 $this->moveFile($pathAdmin.$file, $root.$file, $copy);
			}

			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->whereId(1)->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');

			return redirect('panel/admin')
					->withSuccessUpdate(trans('admin.upgrade_done'));

		}
		//<<---- End Version 2.1 ----->>

		if ($version == '2.2') {

			//============ Starting moving files...
			$oldVersion = '2.1';
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = true;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion  || ! $this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

			if ( ! Schema::hasTable('sessions')) {
				Schema::create('sessions', function ($table) {
						$table->string('id', 191)->unique();
						$table->foreignId('user_id')->nullable();
						$table->string('ip_address', 45)->nullable();
						$table->text('user_agent')->nullable();
						$table->text('payload');
						$table->integer('last_activity');
				});
			}

			Helper::envUpdate('SESSION_DRIVER', 'database');

			if ( ! Schema::hasColumn('users', 'notify_new_tip', 'hide_profile', 'hide_last_seen', 'last_login')) {
				 Schema::table('users', function($table) {
					 $table->enum('notify_new_tip', ['yes', 'no'])->default('yes');
					 $table->enum('hide_profile', ['yes', 'no'])->default('no');
					 $table->enum('hide_last_seen', ['yes', 'no'])->default('no');
					 $table->string('last_login', 250);
				 });
			}

			if ( ! Schema::hasColumn('admin_settings', 'genders')) {
							Schema::table('admin_settings', function($table) {
							 $table->string('genders', 250);
					});
				}

			$this->settings->whereId(1)->update([
 					 'genders' => 'male,female'
 				 ]);

			file_put_contents(
					'.env',
					"\nBACKBLAZE_BUCKET_REGION=\n",
					FILE_APPEND
			);

			// Replace String
			$findStringLang = ');';

			// Ennglish
			$replaceLangEN    = "
			// Version 2.2
			'subscribers' => 'Subscriber|Subscribers',
			'cancel_subscription_ccbill' => 'Cancel your subscription from :ccbill, it will be active until', // Not remove/edit :ccbill
			'genders' => 'Genders',
			'genders_required' => 'The genders field is required.',
			'gay' => 'Gay',
			'lesbian' => 'Lesbian',
			'bisexual' => 'Bisexual',
			'transgender' => 'Transgender',
			'metrosexual' => 'Metrosexual',
			'someone_sent_tip' => 'Someone sent me a tip',
			'privacy_security' => 'Privacy and Security',
			'desc_privacy' => 'Set your privacy',
			'hide_profile' => 'Hide profile',
			'hide_last_seen' => 'Hide last seen',
			'login_sessions' => 'Login sessions',
			'last_login_record' => 'Last login record was from',
			'this_device' => 'This device',
			'last_activity' => 'Last activity',
);";
			$fileLangEN = 'resources/lang/en/general.php';
			@file_put_contents($fileLangEN, str_replace($findStringLang, $replaceLangEN, file_get_contents($fileLangEN)));

		// Español
		$replaceLangES    = "
		// Version 2.2
		'subscribers' => 'Suscriptor|Suscriptores',
		'cancel_subscription_ccbill' => 'Cancele su suscripción desde :ccbill, estará activa hasta', // Not remove/edit :ccbill
		'genders' => 'Géneros',
		'genders_required' => 'Géneros es obligatorio',
		'gay' => 'Gay',
		'lesbian' => 'Lesbiana',
		'bisexual' => 'Bisexual',
		'transgender' => 'Transgénero',
		'metrosexual' => 'Metrosexual',
		'someone_sent_tip' => 'Alguien me ha enviado una propina',
		'privacy_security' => 'Privacidad y seguridad',
		'desc_privacy' => 'Configura tu privacidad',
		'hide_profile' => 'Ocultar perfil',
		'hide_last_seen' => 'Ocultar visto por última vez',
		'login_sessions' => 'Sesiones de inicio de sesión',
		'last_login_record' => 'Último registro de inicio de sesión fue desde',
		'this_device' => 'Este dispositivo',
		'last_activity' => 'Última actividad',
);";
		$fileLangES = 'resources/lang/es/general.php';
		@file_put_contents($fileLangES, str_replace($findStringLang, $replaceLangES, file_get_contents($fileLangES)));


		//============== Files Affected ================//
		$files = [
			'InstallScriptController.php' => $CONTROLLERS,
			'AdminController.php' => $CONTROLLERS,
			'HomeController.php' => $CONTROLLERS,
			'MessagesController.php' => $CONTROLLERS,
			'PaystackController.php' => $CONTROLLERS,
			'SubscriptionsController.php' => $CONTROLLERS,
			'StripeController.php' => $CONTROLLERS,
			'CommentsController.php' => $CONTROLLERS,
			'StripeWebHookController.php' => $CONTROLLERS,
			'AddFundsController.php' => $CONTROLLERS,
			'CCBillController.php' => $CONTROLLERS,
			'UserController.php' => $CONTROLLERS,
			'TipController.php' => $CONTROLLERS,
			'Helper.php' => $APP,
			'app.blade.php' => $VIEWS_LAYOUTS,
			'notifications.blade.php' => $VIEWS_USERS,
			'navbar.blade.php' => $VIEWS_INCLUDES,
			'profile.blade.php' => $VIEWS_USERS,
			'post-detail.blade.php' => $VIEWS_USERS,
			'bookmarks.blade.php' => $VIEWS_USERS,
			'form-post.blade.php' => $VIEWS_INCLUDES,
			'my_subscriptions.blade.php' => $VIEWS_USERS,
			'my_subscribers.blade.php' => $VIEWS_USERS,
			'wallet.blade.php' => $VIEWS_USERS,
			'messages-show.blade.php' => $VIEWS_USERS,
			'payment.js' => $PUBLIC_JS,
			'laravelpwa.php' => $CONFIG,
			'Functions.php' => $TRAITS,
			'serviceworker.js' => $ROOT,
			'home-session.blade.php' => $VIEWS_INDEX,
			'paypal-white.png' => public_path('img'.$DS.'payments').$DS,
			'meta.blade.php' => resource_path('views'.$DS.'vendor'.$DS.'laravelpwa'),
			'web.php' => $ROUTES,
			'bootstrap-icons.css' => $PUBLIC_CSS,
			'bootstrap-icons.woff' => $PUBLIC_FONTS,
			'bootstrap-icons.woff2' => $PUBLIC_FONTS,
			'css_general.blade.php' => $VIEWS_INCLUDES,
			'cards-settings.blade.php' => $VIEWS_INCLUDES,
			'plyr.min.js' => public_path('js'.$DS.'plyr').$DS,
			'plyr.css' => public_path('js'.$DS.'plyr').$DS,
			'plyr.polyfilled.min.js' => public_path('js'.$DS.'plyr').$DS,
			'verify_account.blade.php' => $VIEWS_USERS,
			'select2.min.css' => public_path('plugins'.$DS.'select2').$DS,
			'functions.js' => public_path('admin'.$DS.'js').$DS,
			'edit_my_page.blade.php' => $VIEWS_USERS,
			'Notifications.php' => $MODELS,
			'app-functions.js' => $PUBLIC_JS,
			'dashboard.blade.php' => $VIEWS_USERS,
			'subscription.blade.php' => $VIEWS_USERS,
			'my_cards.blade.php' => $VIEWS_USERS,
			'password.blade.php' => $VIEWS_USERS,
			'my_payments.blade.php' => $VIEWS_USERS,
			'payout_method.blade.php' => $VIEWS_USERS,
			'withdrawals.blade.php' => $VIEWS_USERS,
			'privacy_security.blade.php' => $VIEWS_USERS,
			'javascript_general.blade.php' => $VIEWS_INCLUDES,
			'add_payment_card.blade.php' => $VIEWS_USERS,

			];

			$filesAdmin = [
			'verification.blade.php' => $VIEWS_ADMIN,
			'theme.blade.php' => $VIEWS_ADMIN,
			'edit-member.blade.php' => $VIEWS_ADMIN,
			'storage.blade.php' => $VIEWS_ADMIN,
			'settings.blade.php' => $VIEWS_ADMIN,
		];

			// Files
			foreach ($files as $file => $root) {
				 $this->moveFile($path.$file, $root.$file, $copy);
			}

			// Files Admin
			foreach ($filesAdmin as $file => $root) {
				 $this->moveFile($pathAdmin.$file, $root.$file, $copy);
			}

			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->whereId(1)->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');

			return $upgradeDone;

		}//<<---- End Version 2.2 ----->>

		if ($version == '2.3') {

			//============ Starting moving files...
			$oldVersion = '2.2';
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = true;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion  || ! $this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

			// Create Table PayPerViews
				if ( ! Schema::hasTable('pay_per_views')) {
					Schema::create('pay_per_views', function($table)
							 {
									 $table->increments('id');
									 $table->unsignedInteger('user_id')->index();
									 $table->unsignedInteger('updates_id')->index();
									 $table->unsignedInteger('messages_id')->index();
									 $table->timestamps();
							 });
			 }// <<--- End Create Table PayPerViews

			Schema::table('users', function($table) {
				$table->decimal('price', 10, 2)->change();
			});

			if (! Schema::hasColumn('transactions', 'percentage_applied')) {
							Schema::table('transactions', function($table) {
							 $table->string('percentage_applied', 50);
					});
				}

			if (! Schema::hasColumn('admin_settings', 'cover_default', 'who_can_see_content', 'users_can_edit_post', 'disable_wallet')) {
							Schema::table('admin_settings', function($table) {
							 $table->string('cover_default', 100);
							 $table->enum('who_can_see_content', ['all', 'users'])->default('all');
							 $table->enum('users_can_edit_post', ['on', 'off'])->default('on');
							 $table->enum('disable_wallet', ['on', 'off'])->default('off');
					});
				}

			if (! Schema::hasColumn('users',
					'hide_count_subscribers',
					'hide_my_country',
					'show_my_birthdate',
					'notify_new_post',
					'notify_email_new_post',
					'custom_fee',
					'hide_name'
					)) {
					 Schema::table('users', function($table) {
						 $table->enum('hide_count_subscribers', ['yes', 'no'])->default('no');
						 $table->enum('hide_my_country', ['yes', 'no'])->default('no');
						 $table->enum('show_my_birthdate', ['yes', 'no'])->default('no');
						 $table->enum('notify_new_post', ['yes', 'no'])->default('yes');
						 $table->enum('notify_email_new_post', ['yes', 'no'])->default('no');
						 $table->unsignedInteger('custom_fee');
						 $table->enum('hide_name', ['yes', 'no'])->default('no');
					 });
			}

			// Replace String
			$findStringLang = ');';

			// Ennglish
			$replaceLangEN    = "
			// Version 2.3
			'complete_form_W9_here' => 'Complete IRS W-9 Form here',
			'info_hide_profile' => '(Search, page explore, explore creators)',
			'hide_count_subscribers' => 'Hide number of subscribers',
			'hide_my_country' => 'Hide my country',
			'show_my_birthdate' => 'Show my birthdate',
			'creators_with_free_subscription' => 'Creators with free subscription',
			'cover_default' => 'Cover default',
			'percentage_applied' => 'Percentage applied:',
			'platform' => 'Platform',
			'custom_fee' => 'Custom fee',
			'who_can_see_content' => 'Who can see content?',
			'users_can_edit_post' => 'Users can edit/delete post?',
			'disable_wallet' => 'Disable wallet',
			'error_delete_post' => 'By policies of our platform, you can not delete this post, if you have active subscribers.',
			'set_price_for_post' => 'Set a price for this post, your non-subscribers or free subscribers will have to pay to view it.',
			'set_price_for_msg' => 'Set a price for this message.',
			'hide_name' => 'Show username instead of your Full name',
			'min_ppv_amount' => 'Minimum Pay Per View (Post/Message Locked)',
			'max_ppv_amount' => 'Maximum Pay Per View (Post/Message Locked)',
			'unlock_post_for' => 'Unlock post for',
			'unlock_for' => 'Unlock for',
			'unlock_content' => 'Unlock content',
			'has_bought_your_content' => 'has bought your post',
			'has_bought_your_message' => 'has bought your message',
			'already_purchased_content' => 'You have already purchased this content',
			'purchased' => 'Purchased',
			'not_purchased_any_content' => 'You have not purchased any content',
);";
			$fileLangEN = 'resources/lang/en/general.php';
			@file_put_contents($fileLangEN, str_replace($findStringLang, $replaceLangEN, file_get_contents($fileLangEN)));

		// Español
		$replaceLangES    = "
		// Version 2.3
		'complete_form_W9_here' => 'Complete el formulario W-9 IRS aquí',
		'info_hide_profile' => '(Búsqueda, pagina explorar, explorar creadores)',
		'hide_count_subscribers' => 'Ocultar número de suscriptores',
		'hide_my_country' => 'Ocultar mi país',
		'show_my_birthdate' => 'Mostrar mi fecha de cumpleaños',
		'creators_with_free_subscription' => 'Creadores con suscripciones gratuita',
		'cover_default' => 'Portada predeterminada',
		'percentage_applied' => 'Porcentaje aplicado:',
		'platform' => 'Plataforma',
		'custom_fee' => 'Tarifa personalizada',
		'who_can_see_content' => '¿Quién puede ver el contenido?',
		'users_can_edit_post' => '¿Los usuarios pueden editar/eliminar la publicación?',
		'disable_wallet' => 'Desactivar billetera',
		'error_delete_post' => 'Por políticas de nuestra plataforma, no puede eliminar esta publicación, si tiene suscriptores activos.',
		'set_price_for_post' => 'Establezca un precio para esta publicación, sus no suscriptores o suscriptores gratuitos deberán pagar para verla.',
		'set_price_for_msg' => 'Establezca un precio para este mensaje.',
		'hide_name' => 'Mostrar nombre de usuario en lugar de tu Nombre completo',
		'min_ppv_amount' => 'Pago mínimo por ver (Publicación/Mensaje bloqueado)',
		'max_ppv_amount' => 'Pago máximo por ver (Publicación/Mensaje bloqueado)',
		'unlock_post_for' => 'Desbloquear publicación por',
		'unlock_for' => 'Desbloquear por',
		'unlock_content' => 'Desbloquear contenido',
		'has_bought_your_content' => 'ha comprado tu publicación',
		'has_bought_your_message' => 'ha comprado tu mensaje',
		'already_purchased_content' => 'Ya has comprado este contenido',
		'purchased' => 'Comprado',
		'not_purchased_any_content' => 'No has comprado ningún contenido',
);";
		$fileLangES = 'resources/lang/es/general.php';
		@file_put_contents($fileLangES, str_replace($findStringLang, $replaceLangES, file_get_contents($fileLangES)));


		//============== Files Affected ================//
		$files = [
			'InstallScriptController.php' => $CONTROLLERS,
			'AdminController.php' => $CONTROLLERS,
			'HomeController.php' => $CONTROLLERS,
			'MessagesController.php' => $CONTROLLERS,
			'PaystackController.php' => $CONTROLLERS,
			'PayPalController.php' => $CONTROLLERS,
			'SubscriptionsController.php' => $CONTROLLERS,
			'StripeController.php' => $CONTROLLERS,
			'CommentsController.php' => $CONTROLLERS,
			'StripeWebHookController.php' => $CONTROLLERS,
			'AddFundsController.php' => $CONTROLLERS,
			'CCBillController.php' => $CONTROLLERS,
			'UserController.php' => $CONTROLLERS,
			'TipController.php' => $CONTROLLERS,
			'PayPerViewController.php' => $CONTROLLERS,
			'RegisterController.php' => $CONTROLLERS_AUTH,
			'UpdatesController.php' => $CONTROLLERS,
			'Authenticate.php' => $MIDDLEWARE,
			'PrivateContent.php' => $MIDDLEWARE,
			'Functions.php' => $TRAITS,
			'UserDelete.php' => $TRAITS,
			'PayPerViews.php' => $MODELS,
			'Messages.php' => $MODELS,
			'User.php' => $MODELS,
			'Helper.php' => $APP,
			'SocialAccountService.php' => $APP,
			'app.blade.php' => $VIEWS_LAYOUTS,
			'notifications.blade.php' => $VIEWS_USERS,
			'navbar.blade.php' => $VIEWS_INCLUDES,
			'profile.blade.php' => $VIEWS_USERS,
			'post-detail.blade.php' => $VIEWS_USERS,
			'form-post.blade.php' => $VIEWS_INCLUDES,
			'updates.blade.php' => $VIEWS_INCLUDES,
			'my_subscriptions.blade.php' => $VIEWS_USERS,
			'my_subscribers.blade.php' => $VIEWS_USERS,
			'wallet.blade.php' => $VIEWS_USERS,
			'messages-show.blade.php' => $VIEWS_USERS,
			'messages-inbox.blade.php' => $VIEWS_INCLUDES,
			'messages-chat.blade.php' => $VIEWS_INCLUDES,
			'my-purchases.blade.php' => $VIEWS_USERS,
			'add-funds.js' => $PUBLIC_JS,
			'payment.js' => $PUBLIC_JS,
			'messages.js' => $PUBLIC_JS,
			'payments-ppv.js' => $PUBLIC_JS,
			'plyr.min.js' => public_path('js'.$DS.'plyr').$DS,
			'plyr.polyfilled.min.js' => public_path('js'.$DS.'plyr').$DS,
			'home-session.blade.php' => $VIEWS_INDEX,
			'home-login.blade.php' => $VIEWS_INDEX,
			'creators.blade.php' => $VIEWS_INDEX,
			'categories.blade.php' => $VIEWS_INDEX,
			'post.blade.php' => $VIEWS_INDEX,
			'listing-categories.blade.php' => $VIEWS_INCLUDES,
			'comments.blade.php' => $VIEWS_INCLUDES,
			'web.php' => $ROUTES,
			'css_general.blade.php' => $VIEWS_INCLUDES,
			'cards-settings.blade.php' => $VIEWS_INCLUDES,
			'listing-explore-creators.blade.php' => $VIEWS_INCLUDES,
			'listing-creators.blade.php' => $VIEWS_INCLUDES,
			'verify_account.blade.php' => $VIEWS_USERS,
			'edit_my_page.blade.php' => $VIEWS_USERS,
			'edit-update.blade.php' => $VIEWS_USERS,
			'Notifications.php' => $MODELS,
			'app-functions.js' => $PUBLIC_JS,
			'dashboard.blade.php' => $VIEWS_USERS,
			'subscription.blade.php' => $VIEWS_USERS,
			'my_cards.blade.php' => $VIEWS_USERS,
			'password.blade.php' => $VIEWS_USERS,
			'my_payments.blade.php' => $VIEWS_USERS,
			'payout_method.blade.php' => $VIEWS_USERS,
			'invoice-deposits.blade.php' => $VIEWS_USERS,
			'invoice.blade.php' => $VIEWS_USERS,
			'privacy_security.blade.php' => $VIEWS_USERS,
			'javascript_general.blade.php' => $VIEWS_INCLUDES,
			'Kernel.php' => app_path('Http').$DS,

			];

			$filesAdmin = [
			'verification.blade.php' => $VIEWS_ADMIN,
			'dashboard.blade.php' => $VIEWS_ADMIN,
			'theme.blade.php' => $VIEWS_ADMIN,
			'edit-member.blade.php' => $VIEWS_ADMIN,
			'languages.blade.php' => $VIEWS_ADMIN,
			'settings.blade.php' => $VIEWS_ADMIN,
			'charts.blade.php' => $VIEWS_ADMIN,
			'payments-settings.blade.php' => $VIEWS_ADMIN,
		];

			// Files
			foreach ($files as $file => $root) {
				 $this->moveFile($path.$file, $root.$file, $copy);
			}

			// Files Admin
			foreach ($filesAdmin as $file => $root) {
				 $this->moveFile($pathAdmin.$file, $root.$file, $copy);
			}

			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->whereId(1)->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');

			return $upgradeDone;

		}//<<---- End Version 2.3 ----->>

		if ($version == '2.4') {

			//============ Starting moving files...
			$oldVersion = $this->settings->version;
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = false;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion  || ! $this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

			// Replace String
			$findStringLang = ');';

			// Ennglish
			$replaceLangEN    = "
			// Version 2.4
		'creator' => 'Creator',
		'birthdate_changed_info' => 'Can be edited only once',
		'disable_banner_cookies' => 'Disable cookie policy banner',
		'has_created_new_post' => 'has created a new post',
		'new_post_creators_subscribed' => 'New post of the creators I\'ve subscribed',
		'more_active' => 'More active',
		'more_active_creators' => 'Most active creators',
		'someone_bought_my_content' => 'Someone has bought my content (Post, Message)',
		'sent_you_a_tip_for' => 'sent you a tip for',
		'go_payments_received' => 'Go to payments received',
		'deposit_pending' => 'Deposit pending',
		'view_details_panel_admin' => 'View details in Panel Admin',
		'verification_pending' => 'Verification pending',
		'withdrawal_request' => 'Withdrawal request',
		'note_disable_subs_payment' => 'Note: if you disable, this payment gateway will not be available for subscriptions, tips or Pay Per View, only to recharge the wallet.',
		'active_status_online' => 'Active Status (Online)',
		'wallet_format' => 'Wallet format',
		'credits' => 'Credits',
		'points' => 'Points',
		'tokens' => 'Tokens',
		'real_money' => 'Real money',
		'equivalent_money_format' => '1 credit, point or token equals',
		'credit_equivalent_money' => '1 credit equals',
		'point_equivalent_money' => '1 point equals',
		'token_equivalent_money' => '1 token equals',
		'media_type_upload' => 'Photo, Video or Audio MP3',
		'years' => 'years',
		'price_per_month' => ':price/mo', // Not replace :price
		'maximum_files_post' => 'Maximum files in a Post',
		'maximum_files_msg' => 'Maximum files in a Message',
		'no_binary' => 'Non-binary',
		'ffmpeg_path' => 'FFMPEG path',
		'to_all_my_subscribers' => 'All my subscribers',
		'new_message_all_subscribers' => 'New message all subscribers',
		'great' => 'Great!',
		'msg_success_sent_all_subscribers' => 'The message was successfully sent to all your subscribers',
		'automatically_renewed_wallet' => '* It will be automatically renewed from your wallet balance.',
		'payment_process_wallet' => 'Your payment has been received, if you cannot see it in your account it is being processed.',
		'maximum_selected_categories' => 'You can only select :limit categories', // No remove :limit
		'searching' => 'Searching...',
		'limit_categories' => 'Limit of categories that the user can select',
		'announcements' => 'Announcements',
		'announcement_content' => 'Announcements Content',
		'announcement_info' => 'Accept text, html or javascript. Leave it blank to disable it. (Important: Make sure to close the HTML tags correctly)',
		'show_announcement_to' => 'Show announcement to',
		'all_users' => 'All users',
		'only_creators' => 'Only Creators',
		'no_free_posts' => 'No free posts yet',
		'minimum_photo_width' => 'Minimum photo width',
);";
			$fileLangEN = 'resources/lang/en/general.php';
			@file_put_contents($fileLangEN, str_replace($findStringLang, $replaceLangEN, file_get_contents($fileLangEN)));

		// Español
		$replaceLangES    = "
		// Version 2.4
	'creator' => 'Creador',
	'birthdate_changed_info' => 'Se puede editar sólo una vez',
	'disable_banner_cookies' => 'Desactivar banner de la política de cookies',
	'has_created_new_post' => 'ha creado un nuevo post',
	'new_post_creators_subscribed' => 'Nueva publicación de los creadores que me he suscrito',
	'more_active' => 'Más activo',
	'more_active_creators' => 'Creadores más activo',
	'someone_bought_my_content' => 'Alguien ha comprado mi contenido (Post, Mensaje)',
	'sent_you_a_tip_for' => 'te envió una propina por',
	'go_payments_received' => 'Ir a pagos recibidos',
	'deposit_pending' => 'Depósito pendiente',
	'view_details_panel_admin' => 'Ver detalles en Panel Admin',
	'verification_pending' => 'Verificación pendiente',
	'withdrawal_request' => 'Solicitud de retiro',
	'note_disable_subs_payment' => 'Nota: si desactiva, esta pasarela de pago no estará disponible para suscripciones, propinas o Pago Por Ver, solo para recargar la billetera.',
	'active_status_online' => 'Estado activo (En linea)',
	'wallet_format' => 'Formato de billetera',
	'credits' => 'Créditos',
	'points' => 'Puntos',
	'tokens' => 'Tokens',
	'real_money' => 'Dinero real',
	'equivalent_money' => 'equivale a',
	'credit_equivalent_money' => '1 crédito equivale a',
	'point_equivalent_money' => '1 punto equivale a',
	'token_equivalent_money' => '1 token equivale a',
	'media_type_upload' => 'Foto, Vídeo o Audio MP3',
	'years' => 'años',
	'price_per_month' => ':price/mes', // Not replace :price
	'maximum_files_post' => 'Archivos máximos en un Post',
	'maximum_files_msg' => 'Archivos máximos en un Mensaje',
	'no_binary' => 'No binario',
	'ffmpeg_path' => 'Ruta FFMPEG',
	'to_all_my_subscribers' => 'A todos mis suscriptores',
	'new_message_all_subscribers' => 'Nuevo mensaje a todos los suscriptores',
	'great' => '¡Excelente!',
	'msg_success_sent_all_subscribers' => 'El mensaje fue enviado con éxito a todos tus suscriptores',
	'automatically_renewed_wallet' => '* Será renovada automaticamente del saldo de su billtera.',
	'payment_process_wallet' => 'Su pago ha sido recibido, si no logra verlo en su cuenta está siendo procesado.',
	'maximum_selected_categories' => 'Sólo puedes seleccionar :limit categorías', // No remover :limit
	'searching' => 'Buscando...',
	'limit_categories' => 'Límite de categorias que el usuario puede seleccionar',
	'announcements' => 'Anuncios',
	'announcement_content' => 'Contenido del Anuncio',
	'announcement_info' => 'Acepta texto, html o javascript. Déjelo en blanco para deshabilitarlo. (Importante: Asegurate de cerrar las etiquetas HTML correctamente)',
	'show_announcement_to' => 'Mostrar anuncio a',
	'all_users' => 'Todos los usuarios',
	'only_creators' => 'Solo creadores',
	'no_free_posts' => 'Aún no hay posts gratuitos',
	'minimum_photo_width' => 'Ancho mínimo de la foto',

);";
		$fileLangES = 'resources/lang/es/general.php';
		@file_put_contents($fileLangES, str_replace($findStringLang, $replaceLangES, file_get_contents($fileLangES)));


		//============== Files Affected ================//
		$files = [
			'AddFundsController.php' => $CONTROLLERS,// v2.4
			'AdminController.php' => $CONTROLLERS,// v2.4
			'HomeController.php' => $CONTROLLERS,// v2.4
			'MessagesController.php' => $CONTROLLERS,// v2.4
			'PayPalController.php' => $CONTROLLERS,// v2.4
			'SubscriptionsController.php' => $CONTROLLERS,// v2.4
			'CommentsController.php' => $CONTROLLERS,// v2.4
			'StripeWebHookController.php' => $CONTROLLERS,
			'CCBillController.php' => $CONTROLLERS,// v2.4
			'TipController.php' => $CONTROLLERS,// v2.4
			'PayPerViewController.php' => $CONTROLLERS,// v2.4
			'RegisterController.php' => $CONTROLLERS_AUTH,
			'UpdatesController.php' => $CONTROLLERS,// v2.4
			'UploadMediaController.php' => $CONTROLLERS,// v2.4
			'UploadMediaMessageController.php' => $CONTROLLERS,// v2.4
			'UserController.php' => $CONTROLLERS,// v2.4

			'VerifyCsrfToken.php' => $MIDDLEWARE, // v2.4

			'Messages.php' => $MODELS, // v2.4
			'Media.php' => $MODELS, // v2.4
			'MediaMessages.php' => $MODELS, // v2.4
			'User.php' => $MODELS,// v2.4
			'Transactions.php' => $MODELS,// v2.4
			'Deposits.php' => $MODELS,// v2.4
			'Blogs.php' => $MODELS,// v2.4
			'VerificationRequests.php' => $MODELS,// v2.4
			'Withdrawals.php' => $MODELS,// v2.4
			'Subscriptions.php' => $MODELS,// v2.4
			'Reports.php' => $MODELS,// v2.4
			'Notifications.php' => $MODELS,// v2.4
			'Updates.php' => $MODELS,// v2.4

			'AdminDepositPending.php' => $NOTIFICATIONS,// v2.4
			'AdminVerificationPending.php' => $NOTIFICATIONS,// v2.4
			'AdminWithdrawalPending.php' => $NOTIFICATIONS,// v2.4
			'NewPost.php' => $NOTIFICATIONS,// v2.4
			'PayPerViewReceived.php' => $NOTIFICATIONS,// v2.4
			'TipReceived.php' => $NOTIFICATIONS,// v2.4

			'Functions.php' => $TRAITS,// v2.4
			'UserDelete.php' => $TRAITS,// V2.4

			'Helper.php' => $APP,// v2.4

			'EventServiceProvider.php' => $PROVIDERS,// v2.4

			'app.php' => $CONFIG, // v2.4
			'laravel-ffmpeg.php' => $CONFIG, // v2.4

			'web.php' => $ROUTES, // v2.4

			'app.blade.php' => $VIEWS_LAYOUTS, // v2.4

			'register.blade.php' => $VIEWS_AUTH, // v2.4
			'login.blade.php' => $VIEWS_AUTH, // v2.4
			'email.blade.php' => $VIEWS_AUTH_PASS, // v2.4
			'reset.blade.php' => $VIEWS_AUTH_PASS, // v2.4

			'verify.blade.php' => $VIEWS_EMAILS, // v2.4

			'home-session.blade.php' => $VIEWS_INDEX,// v2.4
			'home-login.blade.php' => $VIEWS_INDEX,// v2.4
			'home.blade.php' => $VIEWS_INDEX, // v2.4
			'creators.blade.php' => $VIEWS_INDEX,// v2.4
			'categories.blade.php' => $VIEWS_INDEX,// v2.4
			'contact.blade.php' => $VIEWS_INDEX,// v2.4
			'explore.blade.php' => $VIEWS_INDEX,// v2.4

			'navbar.blade.php' => $VIEWS_INCLUDES, // v2.4
			'form-post.blade.php' => $VIEWS_INCLUDES,// v2.4
			'footer.blade.php' => $VIEWS_INCLUDES,// v2.4
			'footer-tiny.blade.php' => $VIEWS_INCLUDES,// v2.4
			'updates.blade.php' => $VIEWS_INCLUDES,// v2.4
			'messages-inbox.blade.php' => $VIEWS_INCLUDES,// v2.4
			'messages-chat.blade.php' => $VIEWS_INCLUDES, // v2.4
			'listing-categories.blade.php' => $VIEWS_INCLUDES,// v2.4
			'comments.blade.php' => $VIEWS_INCLUDES, // v2.4
			'css_general.blade.php' => $VIEWS_INCLUDES, // v2.4
			'cards-settings.blade.php' => $VIEWS_INCLUDES, // v2.4
			'listing-explore-creators.blade.php' => $VIEWS_INCLUDES, // v2.4
			'listing-creators.blade.php' => $VIEWS_INCLUDES, // v2.4
			'javascript_general.blade.php' => $VIEWS_INCLUDES, // v2.4
			'media-post.blade.php' => $VIEWS_INCLUDES, // v2.4
			'media-messages.blade.php' => $VIEWS_INCLUDES, // v2.4
			'modal-new-message.blade.php' => $VIEWS_INCLUDES, // v2.4
			'sidebar-messages-inbox.blade.php' => $VIEWS_INCLUDES, // v2.4
			'menu-sidebar-home.blade.php' => $VIEWS_INCLUDES, // v2.4

			'bookmarks.blade.php' =>  $VIEWS_USERS, // v2.4
			'profile.blade.php' => $VIEWS_USERS, // v2.4
			'notifications.blade.php' => $VIEWS_USERS,// v2.4
			'my_subscriptions.blade.php' => $VIEWS_USERS,// v2.4
			'my_subscribers.blade.php' => $VIEWS_USERS,// v2.4
			'wallet.blade.php' => $VIEWS_USERS,// v2.4
			'messages-show.blade.php' => $VIEWS_USERS,// v2.4
			'messages.blade.php' => $VIEWS_USERS,// v2.4
			'my-purchases.blade.php' => $VIEWS_USERS,// V2.4
			'edit_my_page.blade.php' => $VIEWS_USERS,// v2.4
			'edit-update.blade.php' => $VIEWS_USERS,// v2.4
			'dashboard.blade.php' => $VIEWS_USERS,// v2.4
			'subscription.blade.php' => $VIEWS_USERS, // v2.4
			'my-purchases.blade.php' => $VIEWS_USERS,// v2.4
			'password.blade.php' => $VIEWS_USERS, // v2.4
			'my_payments.blade.php' => $VIEWS_USERS, // v2.4
			'privacy_security.blade.php' => $VIEWS_USERS, // v2.4
			'delete_account.blade.php' => $VIEWS_USERS, // v2.4
			'header.blade.php' => resource_path('views'.$DS.'vendor'.$DS.'mail'.$DS.'html').$DS,// v2.4
			'message.blade.php' => resource_path('views'.$DS.'vendor'.$DS.'mail'.$DS.'html').$DS,// v2.4
			'button.blade.php' => resource_path('views'.$DS.'vendor'.$DS.'mail'.$DS.'html').$DS,// v2.4
			'email.blade.php' => resource_path('views'.$DS.'vendor'.$DS.'notifications').$DS,// v2.4
			'loadmore.blade.php' => resource_path('views'.$DS.'vendor'.$DS.'pagination').$DS,// v2.4

			'add-funds.js' => $PUBLIC_JS,// v2.4
			'payment.js' => $PUBLIC_JS,// v2.4
			'messages.js' => $PUBLIC_JS,// v2.4
			'payments-ppv.js' => $PUBLIC_JS,// v2.4
			'core.min.js' => $PUBLIC_JS,// v2.4
			'paginator-messages.js' => $PUBLIC_JS,// v2.4
			'core.min.css' => $PUBLIC_CSS,// v2.4
			'app-functions.js' => $PUBLIC_JS, // v2.4
			'functions.js' => $PUBLIC_JS_ADMIN, // v2.4
			'swiper-bundle.min.js.map' => $PUBLIC_JS, // v2.4
			'bootstrap-icons.css' => $PUBLIC_CSS, // v2.4
			'bootstrap-icons.woff' => $PUBLIC_FONTS, // v2.4
			'bootstrap-icons.woff2' => $PUBLIC_FONTS, // v2.4
			'plyr.css' => public_path('js'.$DS.'plyr').$DS, // v2.4

			'popular.png' => $PUBLIC_IMG, // v2.4
			'featured.png' => $PUBLIC_IMG, // v2.4
			'more-active.png' => $PUBLIC_IMG, // v2.4
			'creators.png' => $PUBLIC_IMG, // v2.4
			'unlock.png' => $PUBLIC_IMG, // v2.4
			'coinpayments.png' => public_path('img'.$DS.'payments').$DS, // v2.4
			'coinpayments-white.png' => public_path('img'.$DS.'payments').$DS, // v2.4

			'Kernel.php' => app_path('Console').$DS,// v2.4

			];

			$filesAdmin = [
			'edit-blog.blade.php' => $VIEWS_ADMIN, // v2.4
			'blog.blade.php' => $VIEWS_ADMIN, // v2.4
			'create-blog.blade.php' => $VIEWS_ADMIN, // v2.4
			'verification.blade.php' => $VIEWS_ADMIN, // v2.4
			'dashboard.blade.php' => $VIEWS_ADMIN,// v2.4
			'edit-member.blade.php' => $VIEWS_ADMIN,// v2.4
			'settings.blade.php' => $VIEWS_ADMIN, // v2.4
			'charts.blade.php' => $VIEWS_ADMIN, // v2.4
			'posts.blade.php' => $VIEWS_ADMIN,// v2.4
			'email-settings.blade.php' => $VIEWS_ADMIN, // v2.4
			'payments-settings.blade.php' => $VIEWS_ADMIN,
			'subscriptions.blade.php' => $VIEWS_ADMIN, // v2.4
			'transactions.blade.php' => $VIEWS_ADMIN, // v2.4
			'paypal-settings.blade.php' => $VIEWS_ADMIN,// v2.4
			'coinpayments-settings.blade.php' => $VIEWS_ADMIN,// v2.4
			'paystack-settings.blade.php' => $VIEWS_ADMIN,// v2.4
			'stripe-settings.blade.php' => $VIEWS_ADMIN,// v2.4
			'ccbill-settings.blade.php' => $VIEWS_ADMIN,// v2.4
			'limits.blade.php' => $VIEWS_ADMIN,// v2.4
			'deposits-view.blade.php' => $VIEWS_ADMIN,// v2.4
			'members.blade.php' => $VIEWS_ADMIN,// v2.4
			'layout.blade.php' => $VIEWS_ADMIN,// v2.4
			'announcements.blade.php' => $VIEWS_ADMIN,// v2.4
		];

			// Files
			foreach ($files as $file => $root) {
				 $this->moveFile($path.$file, $root.$file, $copy);
			}

			// Files Admin
			foreach ($filesAdmin as $file => $root) {
				 $this->moveFile($pathAdmin.$file, $root.$file, $copy);
			}

			// Copy Folders

			// Events
			$filePathFolderEvents = $path.'Events';
			$pathFolderEvents = app_path('Events').$DS;

			$this->moveDirectory($filePathFolderEvents, $pathFolderEvents, $copy);

			// Listeners
			$filePathFolderListeners = $path.'Listeners';
			$pathFolderListeners = app_path('Listeners').$DS;

			$this->moveDirectory($filePathFolderListeners, $pathFolderListeners, $copy);

			// Jobs
			$filePathFolderJobs = $path.'Jobs';
			$pathFolderJobs = app_path('Jobs').$DS;

			$this->moveDirectory($filePathFolderJobs, $pathFolderJobs, $copy);

			// Fileuploader
			$filePathFolderFileuploader = $path.'fileuploader';
			$pathFolderFileuploader = public_path('js'.$DS.'fileuploader').$DS;

			$this->moveDirectory($filePathFolderFileuploader, $pathFolderFileuploader, $copy);

			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

		 // Start v2.4 ====================================

		 $this->settings->update([
					'min_width_height_image' => '400'
				]);

		 // Create Table Media
			 if (! Schema::hasTable('media')) {
				 Schema::create('media', function($table)
							{
								$table->bigIncrements('id');
								$table->unsignedInteger('updates_id')->index();
								$table->unsignedInteger('user_id')->index();
								$table->string('type', 100)->index();
								$table->string('image');
								$table->string('width', 5)->nullable();
								$table->string('height', 5)->nullable();
								$table->string('img_type');
								$table->string('video');
								$table->string('video_poster')->nullable();
								$table->string('video_embed', 200);
								$table->string('music');
								$table->string('file');
								$table->string('file_name');
								$table->string('file_size');
								$table->string('token')->index();
								$table->enum('status', ['active', 'pending'])->default('active');
								$table->timestamps();
							});
			}// <<--- End Create Table Media

			// Move all media to new table
			if (Schema::hasTable('media')) {

				$allUpdates = Updates::where('image', '<>', '')
		    ->orWhere('video', '<>', '')
		    ->orWhere('music', '<>', '')
		    ->orWhere('file', '<>', '')
		    ->orWhere('video_embed', '<>', '')
		    ->get();

		    if ($allUpdates) {
		      foreach ($allUpdates as $key) {

		       if ($key->image) {
		         $type = 'image';
		       }

		       if ($key->video) {
		         $type = 'video';
		       }

		       if ($key->music) {
		         $type = 'music';
		       }

		       if ($key->file) {
		         $type = 'file';
		       }

		       if ($key->video_embed) {
		         $type = 'video';
		       }

		       $data[] = [
		       'updates_id' => $key->id,
		       'user_id' => $key->user_id,
		       'type' => $type,
		       'image' => $key->image,
		       'width' => null,
		       'height' => null,
		       'video' => $key->video,
		       'video_poster' => null,
		       'video_embed' => $key->video_embed,
		       'music' => $key->music,
		       'file' => $key->file,
		       'file_name' => $key->file_name,
		       'file_size' => $key->file_size,
		       'img_type' => $key->img_type,
		       'token' => $key->token_id,
		       'created_at' => now()
		     ];
		   }

		   if (isset($data)) {

		     foreach (array_chunk($data, 500) as $key => $smlArray) {
		          foreach ($smlArray as $index => $value) {
		                  $tmp[$index] = $value;
		          }
		          Media::insert($tmp);
		      }
		    }
		 }// allUpdates

			}// <<--- Move all media to new table

			// Create Table Media Messages
				if (! Schema::hasTable('media_messages')) {
					Schema::create('media_messages', function($table)
							 {
								 $table->bigIncrements('id');
								 $table->unsignedInteger('messages_id')->index();
								 $table->string('type', 100)->index();
								 $table->string('file');
								 $table->string('width', 5)->nullable();
								 $table->string('height', 5)->nullable();
								 $table->string('video_poster')->nullable();
								 $table->string('file_name');
								 $table->string('file_size');
								 $table->string('token')->index();
								 $table->enum('status', ['active', 'pending'])->default('active');
								 $table->timestamps();
							 });
			 }// <<--- End Create Table Media Messages

			 // Move all media messages to new table
			 if (Schema::hasTable('media_messages')) {
				 $allMessages = Messages::where('file', '<>', '')->get();

				 if ($allMessages) {
					 foreach ($allMessages as $key) {

						 $dataMessages[] = [
						 'messages_id' => $key->id,
						 'type' => $key->format,
						 'file' => $key->file,
						 'width' => null,
						 'height' => null,
						 'video_poster' => null,
						 'file_name' => $key->original_name,
						 'file_size' => $key->size,
						 'token' => str_random(150).uniqid().now()->timestamp,
						 'created_at' => now()
					 ];
				 }

				 if (isset($dataMessages)) {
					 foreach (array_chunk($dataMessages, 500) as $key => $smlArray) {
			          foreach ($smlArray as $index => $value) {
			                  $tmp[$index] = $value;
			          }
			          MediaMessages::insert($tmp);
			      }
				 }
			 }// allMessages

			 }// <<--- Move all media messages to new table

		 if (! Schema::hasColumn('users', 'birthdate_changed','email_new_tip', 'email_new_ppv')) {
			 Schema::table('users', function($table) {
				 $table->enum('birthdate_changed', ['yes', 'no'])->default('no');
				 $table->enum('email_new_tip', ['yes', 'no'])->default('yes');
				 $table->enum('email_new_ppv', ['yes', 'no'])->default('yes');
				 $table->enum('notify_new_ppv', ['yes', 'no'])->default('yes');
				 $table->enum('active_status_online', ['yes', 'no'])->default('yes');
			 });
		 }

		 Schema::table('users', function($table) {
			 $table->dropColumn('created_at');
			 $table->dropColumn('updated_at');

	 });

		 Schema::table('users', function($table) {
				 $table->string('name', 150)->change();
		 });

		 Schema::table('admin_settings', function($table) {
				 $table->dropColumn('announcements');
		 });

		 if (! Schema::hasColumn('admin_settings',
				 'disable_banner_cookies',
				 'wallet_format',
				 'maximum_files_post',
				 'maximum_files_msg',
				 'announcement',
				 'announcement_show',
				 'announcement_cookie',
				 'limit_categories',
				 'ffmpeg_path'
			 )) {
						 Schema::table('admin_settings', function($table) {
							$table->enum('disable_banner_cookies', ['on', 'off'])->default('off');
							$table->enum('wallet_format', ['real_money', 'credits', 'points', 'tokens'])->default('real_money');
							$table->unsignedInteger('maximum_files_post')->default(5);
							$table->unsignedInteger('maximum_files_msg')->default(5);
							$table->longText('announcement')->collation('utf8mb4_unicode_ci');
							$table->string('announcement_show', 100);
							$table->string('announcement_cookie', 20);
							$table->unsignedInteger('limit_categories')->default(3);
							$table->string('ffmpeg_path');
				 });
			 }

			 if (! Schema::hasColumn('subscriptions', 'rebill_wallet')) {
							 Schema::table('subscriptions', function($table) {
								$table->enum('rebill_wallet', ['on', 'off'])->default('off');
					 });
				 }

		 Schema::table('users', function($table) {
				 $table->string('categories_id')->change();
		 });

		 DB::statement('ALTER TABLE pages MODIFY COLUMN content MEDIUMTEXT');
		 DB::statement('ALTER TABLE users CHANGE featured_date featured_date TIMESTAMP NULL DEFAULT NULL');
		 DB::statement("ALTER TABLE users CHANGE notify_email_new_post notify_email_new_post ENUM('yes','no') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'yes'");

		 @file_put_contents(
				 '.env',
				 "\nQUEUE_CONNECTION=database\n\nFFMPEG_PATH=\"\"",
				 FILE_APPEND
		 );

		 // Create Table Jobs
			 if (! Schema::hasTable('jobs')) {
				 Schema::create('jobs', function($table)
							{
								$table->bigIncrements('id');
								$table->string('queue')->index();
								$table->longText('payload');
								$table->unsignedTinyInteger('attempts');
								$table->unsignedInteger('reserved_at')->nullable();
								$table->unsignedInteger('available_at');
								$table->unsignedInteger('created_at');
							});
			}// <<--- End Create Table Jobs

			// Create Table Failed Jobs
			 if (! Schema::hasTable('failed_jobs')) {
				 Schema::create('failed_jobs', function($table)
							{
								$table->id();
								$table->text('connection');
								$table->text('queue');
								$table->longText('payload');
								$table->longText('exception');
								$table->timestamp('failed_at')->useCurrent();
							});
			}// <<--- End Create Table Failed Jobs

			// Add Artisan and Explore as a reserved name
			if (! Schema::hasColumn('reserved', 'artisan', 'explore')) {
				 \DB::table('reserved')->insert([
					 ['name' => 'artisan'],
					 ['name' => 'explore']
				 ]);
			 }// <<--- End

			 if (Schema::hasTable('payment_gateways')) {
					 \DB::table('payment_gateways')->insert([
						 [
							 'name' => 'Coinpayments',
							 'type' => 'normal',
							 'enabled' => '0',
							 'fee' => 0.0,
							 'fee_cents' => 0.00,
							 'email' => '',
							 'key' => '',
							 'key_secret' => '',
							 'recurrent' => 'no',
							 'logo' => 'coinpayments.png',
							 'subscription' => 'no',
							 'bank_info' => '',
							 'token' => str_random(150),
					 ]
				 ]
			 );
		 }// End add Coinpayments

		 // End Query v2.4 ====================================

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');

			return $upgradeDone;

		}//<<---- End Version 2.4 ----->>

		if ($version == '2.5') {

			//============ Starting moving files...
			$oldVersion = $this->settings->version;
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = true;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion  || ! $this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

		//============== Files Affected ================//
		$files = [
			'AddFundsController.php' => $CONTROLLERS,// v2.5
			'AdminController.php' => $CONTROLLERS,// v2.5
			'HomeController.php' => $CONTROLLERS,// v2.5
			'PayPalController.php' => $CONTROLLERS,// v2.5
			'CommentsController.php' => $CONTROLLERS,// v2.5
			'InstallScriptController.php' => $CONTROLLERS,// v2.5
			'UpdatesController.php' => $CONTROLLERS,// v2.5
			'UserController.php' => $CONTROLLERS,// v2.5

			'Comments.php' => $MODELS,// v2.5
			'CommentsLikes.php' => $MODELS,// v2.5

			'UserDelete.php' => $TRAITS,// v2.5

			'app.blade.php' => $VIEWS_LAYOUTS,// v2.5

			'contact.blade.php' => $VIEWS_INDEX,// v2.5
			'explore.blade.php' => $VIEWS_INDEX,// v2.5

			'navbar.blade.php' => $VIEWS_INCLUDES,// v2.5
			'form-post.blade.php' => $VIEWS_INCLUDES,// v2.5
			'comments.blade.php' => $VIEWS_INCLUDES,// v2.5
			'css_general.blade.php' => $VIEWS_INCLUDES,// v2.5
			'javascript_general.blade.php' => $VIEWS_INCLUDES,// v2.5
			'media-post.blade.php' => $VIEWS_INCLUDES,// v2.5
			'media-messages.blade.php' => $VIEWS_INCLUDES,// v2.5
			'modal-new-message.blade.php' => $VIEWS_INCLUDES,// v2.5
			'sidebar-messages-inbox.blade.php' => $VIEWS_INCLUDES,
			'menu-sidebar-home.blade.php' => $VIEWS_INCLUDES,// v2.5
			'updates.blade.php' => $VIEWS_INCLUDES,// v2.5

			'requirements.blade.php' => $VIEWS_INSTALL,// v2.5

			'profile.blade.php' => $VIEWS_USERS,// v2.5
			'post-detail.blade.php' => $VIEWS_USERS,// v2.5
			'notifications.blade.php' => $VIEWS_USERS,// v2.5
			'messages-show.blade.php' => $VIEWS_USERS,// v2.5
			'payout_method.blade.php' => $VIEWS_USERS,// v2.5

			'app-functions.js' => $PUBLIC_JS,// v2.5
			'payoneer.png' => public_path('img'.$DS.'payments').$DS, // v2.5
			'payoneer-white.png' => public_path('img'.$DS.'payments').$DS, // v2.5
			'zelle.png' => public_path('img'.$DS.'payments').$DS, // v2.5
			'zelle-white.png' => public_path('img'.$DS.'payments').$DS, // v2.5

			];

			$filesAdmin = [
			'edit-member.blade.php' => $VIEWS_ADMIN,// v2.5
			'settings.blade.php' => $VIEWS_ADMIN,// v2.5
			'payments-settings.blade.php' => $VIEWS_ADMIN,// v2.5
			'coinpayments-settings.blade.php' => $VIEWS_ADMIN,// v2.5
		];

			// Files
			foreach ($files as $file => $root) {
				 $this->moveFile($path.$file, $root.$file, $copy);
			}

			// Files Admin
			foreach ($filesAdmin as $file => $root) {
				 $this->moveFile($pathAdmin.$file, $root.$file, $copy);
			}

			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

		 //============== Start Query v2.5 ====================================

		 // Replace String
		 $findStringLang = ');';

		 // Ennglish
		 $replaceLangEN    = "
		 // Version 2.5
 'price_post_ppv' => 'Set a price for this post',
 'captcha_contact' => 'Captcha on Page Contact us',
 'disable_tips' => 'Disable tips',
 'payout_method_info' => 'Select the payment method you want to receive your earnings.',
 'processor_fees_may_apply' => 'Some processor fees may apply',
 'email_payoneer' => 'Email Payoneer',
 'confirm_email_payoneer' => 'Confirm Email Payoneer',
 'email_zelle' => 'Email Zelle',
 'confirm_email_zelle' => 'Confirm Email Zelle',
 'liked_your_comment' => 'liked your comment in',
 'someone_liked_comment' => 'Someone liked your comment',
);";
		 $fileLangEN = 'resources/lang/en/general.php';
		 @file_put_contents($fileLangEN, str_replace($findStringLang, $replaceLangEN, file_get_contents($fileLangEN)));

	 // Español
	 $replaceLangES    = "
	 // Version 2.5
 'price_post_ppv' => 'Establezca un precio para esta publicación',
 'captcha_contact' => 'Captcha en Página Contáctenos',
 'disable_tips' => 'Desactivar propinas',
 'payout_method_info' => 'Selecciona el método de pago que deseas recibir tus ganancias.',
 'processor_fees_may_apply' => 'Es posible que se apliquen algunas tarifas del procesador.',
 'email_payoneer' => 'Email de Payoneer',
 'confirm_email_payoneer' => 'Confirmar correo Payoneer',
 'email_zelle' => 'Email de Zelle',
 'confirm_email_zelle' => 'Confirmar correo Zelle',
 'liked_your_comment' => 'le gustó tu comentario en',
 'someone_liked_comment' => 'A alguien le gustó tu comentario',
);";
	 $fileLangES = 'resources/lang/es/general.php';
	 @file_put_contents($fileLangES, str_replace($findStringLang, $replaceLangES, file_get_contents($fileLangES)));

	 @file_put_contents(
			 'routes/web.php',
			 "
Route::post('comment/like','CommentsController@like')->middleware('auth');",
			 FILE_APPEND
	 );

		 if (! Schema::hasColumn('users',
		 'payoneer_account',
		 'zelle_account'
		 )) {
			 Schema::table('users', function($table) {
				 $table->string('payoneer_account', 200);
				 $table->string('zelle_account', 200);
				 $table->enum('notify_liked_comment', ['yes', 'no'])->default('yes');
			 });
		 }

		 if (! Schema::hasColumn('admin_settings',
				 'captcha_contact',
				 'disable_tips'
			 )) {
						 Schema::table('admin_settings', function($table) {
							$table->enum('captcha_contact', ['on', 'off'])->default('on');
							$table->enum('disable_tips', ['on', 'off'])->default('off');
							$table->enum('payout_method_payoneer', ['on', 'off'])->default('off');
							$table->enum('payout_method_zelle', ['on', 'off'])->default('off');
				 });
			 }

			 Schema::table('admin_settings', function($table) {
	       $table->dropColumn('ffmpeg_path');
	   });

		 // Create Table Comments Likes
			 if (! Schema::hasTable('comments_likes')) {
				 Schema::create('comments_likes', function($table)
							{
									$table->increments('id');
									$table->unsignedInteger('user_id')->index();
									$table->unsignedInteger('comments_id')->index();
									$table->timestamps();
							});
			}// <<--- End Create Table Bookmarks

		 //=============== End Query v2.5 ====================================

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');

			return $upgradeDone;

		}//<<---- End Version 2.5 ----->>

		if ($version == '2.6') {

			//============ Starting moving files...
			$oldVersion = $this->settings->version;
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = true;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion  || ! $this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

		//============== Files Affected ================//
		$files = [
			'serviceworker.js' => $ROOT,// v2.6
			'Helper.php' => $APP,// v2.6
			'PostRejected.php' => $NOTIFICATIONS,// v2.6
			'queue.php' => $CONFIG,// v2.6
			'web.php' => $ROUTES,// v2.6
			'Kernel.php' => app_path('Http').$DS,// v2.6

			//============ CONTROLLERS =================//
			'AddFundsController.php' => $CONTROLLERS,// v2.6
			'AdminController.php' => $CONTROLLERS,// v2.6
			'CCBillController.php' => $CONTROLLERS,//v2.6
			'HomeController.php' => $CONTROLLERS,// v2.6
			'InstallScriptController.php' => $CONTROLLERS,// v2.6
			'PayPalController.php' => $CONTROLLERS,// v2.6
			'TipController.php' => $CONTROLLERS,// v2.6
			'CommentsController.php' => $CONTROLLERS,
			'SubscriptionsController.php' => $CONTROLLERS,// v2.6
			'UploadMediaController.php' => $CONTROLLERS,// v2.6
			'UploadMediaMessageController.php' => $CONTROLLERS,// v2.6
			'UpdatesController.php' => $CONTROLLERS,// v2.6
			'UserController.php' => $CONTROLLERS,// v2.6
			'MessagesController.php' => $CONTROLLERS,// v2.6
			'RegisterController.php' => $CONTROLLERS,// v2.6

			'Role.php' => $MIDDLEWARE,// v2.6
			'UserCountry.php' => $MIDDLEWARE,// v2.6

			'DeleteMedia.php' => $JOBS, // v2.6
			'EncodeVideo.php' => $JOBS, // v2.6
			'EncodeVideoMessages.php' => $JOBS, // v2.6

			'Comments.php' => $MODELS,
			'User.php' => $MODELS,// v2.6
			'Conversations.php' => $MODELS,// v2.6

			'Functions.php' => $TRAITS,// v2.6
			'UserDelete.php' => $TRAITS,// v2.6

			//============ PUBLIC =================//

			'app-functions.js' => $PUBLIC_JS,// v2.6
			'messages.js' => $PUBLIC_JS,// v2.6
			'core.min.js' => $PUBLIC_JS,// v2.6

			'functions.js' => $PUBLIC_JS_ADMIN,// v2.6
			'AdminLTE.min.css' => $PUBLIC_CSS_ADMIN,// v2.6
			'app.css' => $PUBLIC_CSS_ADMIN,// v2.6
			'fileuploader-msg.js' => public_path('js'.$DS.'fileuploader').$DS,// v2.6
			'fileuploader-post.js' => public_path('js'.$DS.'fileuploader').$DS,// v2.6

			//=========== VIEWS ===================//

			'register.blade.php' => $VIEWS_AUTH,// v2.6

			'app.blade.php' => $VIEWS_LAYOUTS,// v2.6

			'blog.blade.php' => $VIEWS_INDEX,// v2.6
			'explore.blade.php' => $VIEWS_INDEX,
			'home-session.blade.php' => $VIEWS_INDEX,// v2.6
			'home.blade.php' => $VIEWS_INDEX,// v2.6
			'post.blade.php' => $VIEWS_INDEX,// v2.6

			'navbar.blade.php' => $VIEWS_INCLUDES,// v2.6
			'messages-chat.blade.php' => $VIEWS_INCLUDES,// v2.6
			'comments.blade.php' => $VIEWS_INCLUDES,
			'css_general.blade.php' => $VIEWS_INCLUDES,// v2.6
			'javascript_general.blade.php' => $VIEWS_INCLUDES,
			'media-post.blade.php' => $VIEWS_INCLUDES,
			'media-messages.blade.php' => $VIEWS_INCLUDES,// v2.6
			'sidebar-messages-inbox.blade.php' => $VIEWS_INCLUDES,
			'form-post.blade.php' => $VIEWS_INCLUDES,// v2.6
			'updates.blade.php' => $VIEWS_INCLUDES,// v2.6
			'css_admin.blade.php' => $VIEWS_INCLUDES,// v2.6
			'cards-settings.blade.php' => $VIEWS_INCLUDES,// v2.6
			'modal-new-message.blade.php' => $VIEWS_INCLUDES,// v2.6

			'requirements.blade.php' => $VIEWS_INSTALL,

			'dashboard.blade.php' => $VIEWS_USERS,// v2.6
			'profile.blade.php' => $VIEWS_USERS,// v2.6
			'invoice-deposits.blade.php' => $VIEWS_USERS,// v2.6
			'invoice.blade.php' => $VIEWS_USERS,// v2.6
			'notifications.blade.php' => $VIEWS_USERS,// v2.6
			'messages-show.blade.php' => $VIEWS_USERS,// v2.6
			'my-purchases.blade.php' => $VIEWS_USERS,// v2.6
			'wallet.blade.php' => $VIEWS_USERS,// v2.6
			'my_posts.blade.php' => $VIEWS_USERS,// v2.6
			'edit_my_page.blade.php' => $VIEWS_USERS,// v2.6
			'block_countries.blade.php' => $VIEWS_USERS,// v2.6

			'meta.blade.php' => resource_path('views'.$DS.'vendor'.$DS.'laravelpwa'), // v2.6

			];

			$filesAdmin = [
			'charts.blade.php' => $VIEWS_ADMIN,// v2.6
			'dashboard.blade.php' => $VIEWS_ADMIN,// v2.6
			'announcements.blade.php' => $VIEWS_ADMIN,// v2.6
			'limits.blade.php' => $VIEWS_ADMIN,// v2.6
			'posts.blade.php' => $VIEWS_ADMIN,// v2.6
			'edit-member.blade.php' => $VIEWS_ADMIN,// v2.6
			'members.blade.php' => $VIEWS_ADMIN,// v2.6
			'role-and-permissions-member.blade.php' => $VIEWS_ADMIN,// v2.6
			'layout.blade.php' => $VIEWS_ADMIN,// v2.6
			'blog.blade.php' => $VIEWS_ADMIN,// v2.6
			'languages.blade.php' => $VIEWS_ADMIN,// v2.6
			'edit-languages.blade.php' => $VIEWS_ADMIN,// v2.6
			'pages.blade.php' => $VIEWS_ADMIN,// v2.6
			'edit-pages.blade.php' => $VIEWS_ADMIN,// v2.6
			'add-page.blade.php' => $VIEWS_ADMIN,// v2.6
			'categories.blade.php' => $VIEWS_ADMIN,// v2.6
			'unauthorized.blade.php' => $VIEWS_ADMIN,// v2.6
			'settings.blade.php' => $VIEWS_ADMIN,// v2.6
		];

			// Files
			foreach ($files as $file => $root) {
				 $this->moveFile($path.$file, $root.$file, $copy);
			}

			// Files Admin
			foreach ($filesAdmin as $file => $root) {
				 $this->moveFile($pathAdmin.$file, $root.$file, $copy);
			}

			// Folder Console
			$filePathFolderConsole = $path.'Console';
			$pathFolderConsole = app_path('Console').$DS;

			File::deleteDirectory($pathFolderConsole);

			$this->moveDirectory($filePathFolderConsole, $pathFolderConsole, $copy);

			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

		 //============== Start Query v2.6 ====================================

		 // Replace String
		 $findStringLang = ');';

		 // Ennglish
		 $replaceLangEN    = "
		 // Version 2.6
	 'explore_posts' => 'Explore Posts',
	 'transaction_fee_info' => '* Transaction fee is not included in the amount, only on invoice.',
	 'type_announcement' => 'Type announcement',
	 'informative' => 'Informative',
	 'important' => 'Important',
	 'compared_yesterday' => 'Compared to yesterday',
	 'compared_last_week' => 'Compared to last week',
	 'compared_last_month' => 'Compared to last month',
	 'auto_approve_post' => 'Auto approve Post',
	 'post_pending_review' => 'Post pending review',
	 'alert_post_pending_review' => 'Your publication will be available after it is reviewed, you can see in',
	 'my_posts' => 'My Posts',
	 'yes_confirm_reject_post' => 'Yes, reject post!',
	 'yes_confirm_approve_post' => 'Yes, approve post!',
	 'delete_confirm_post' => 'An email will be sent to the user notifying that their post was rejected.',
	 'approve_confirm_post' => 'An notification will be sent to the user notifying that their post was approved.',
	 'rejected_post' => 'Post Rejected',
	 'approve_post_success' => 'Post has been approved successfully!',
	 'line_rejected_post' => 'Your post \":title\" was rejected because it does not meet our terms and conditions.', // Do not remove :title
	 'has_approved_your_post' => 'Your post has been approved',
	 'all_post_created' => 'All the posts you have created',
	 'interactions' => 'Interactions',
	 'not_post_created' => 'You have not created any post so far',
	 'role_and_permissions' => 'Role and permissions',
	 'can_see' => 'Can see (Read only)',
	 'can_crud' => 'Can Create, Read, Update, Approve, Delete, etc.',
	 'can_see_post_blocked' => 'See blocked posts or premium (PPV)',
	 'info_can_see_post_blocked' => 'If you give access to manage posts you must select \"Yes\"',
	 'limited_access' => 'Limited Access',
	 'info_limited_access' => 'The user will be able to access all the sections of the Panel Admin, but will not be able to add, edit or delete anything.',
	 'give_access_error' => 'To give access to a section you must uncheck the Limited Access option',
	 'select_all' => 'Select all',
	 'unauthorized_action' => 'You are not authorized to perform this action',
	 'unauthorized_section' => 'You do not have permission to view this section, go to the available sections found in the left menu.',
	 'block_countries' => 'Block Countries',
	 'block_countries_info' => 'Select the countries in which you do not want your profile to be displayed, they will not be able to see your profile in any section of the site.',
	 'super_admin' => 'Super Admin',
	 'couple' => 'Couple',
	 'video_on_way' => 'Video on the way...',
	 'video_processed_info' => 'Your video is being processed, you will receive a notification when it is ready.',
	 'video_processed_successfully_post' => 'Your video has been processed successfully (Post)',
	 'video_processed_successfully_message' => 'Your video has been processed successfully (Message)',
);";
		 $fileLangEN = 'resources/lang/en/general.php';
		 @file_put_contents($fileLangEN, str_replace($findStringLang, $replaceLangEN, file_get_contents($fileLangEN)));

	 // Español
	 $replaceLangES    = "
	 // Version 2.6
 'explore_posts' => 'Explorar Posts',
 'transaction_fee_info' => '* La tarifa de transacción no está incluida en el monto, solo en factura.',
 'type_announcement' => 'Tipo de anuncio',
 'informative' => 'Informativo',
 'important' => 'Importante',
 'compared_yesterday' => 'Comparado con ayer',
 'compared_last_week' => 'Comparado con la semana pasada',
 'compared_last_month' => 'Comparado con el mes pasado',
 'auto_approve_post' => 'Auto aprobar Publicación',
 'post_pending_review' => 'Publicación pendiente de revision',
 'alert_post_pending_review' => 'Tu publicación estará disponible despues que sea revisada, puedes ver en',
 'my_posts' => 'Mis Posts',
 'yes_confirm_reject_post' => 'Sí, ¡rechazar post!',
 'yes_confirm_approve_post' => 'Sí, ¡aprobar post!',
 'delete_confirm_post' => 'Se enviará un correo electrónico al usuario notificando que su publicación fue rechazada.',
 'approve_confirm_post' => 'Se enviará una notificación al usuario notificando que su publicación fue aprobada.',
 'rejected_post' => 'Post Rechazado',
 'approve_post_success' => '¡Post ha sido aprobado con éxito!',
 'line_rejected_post' => 'Su post \":title\" fue rechazado porque no cumple con nuestros términos y condiciones.', // Do not remove :title
 'has_approved_your_post' => 'Tu post ha sido aprobado',
 'all_post_created' => 'Todos los posts que has creado',
 'interactions' => 'Interacciones',
 'not_post_created' => 'No has creado ningún post hasta el momento',
 'role_and_permissions' => 'Rol y permisos',
 'can_see' => 'Puede ver (Solo lectura)',
 'can_crud' => 'Puede Crear, Leer, Actualizar Aprobar, Borrar, etc.',
 'can_see_post_blocked' => 'Ver posts bloqueados o premium (PPV)',
 'info_can_see_post_blocked' => 'Si das acceso a manejar posts debes seleccionar \"Sí\"',
 'limited_access' => 'Acceso Limitado',
 'info_limited_access' => 'El usuario podrá acceder a todas las secciones del Panel Admin, pero no podrá agregar, editar o eliminar nada.',
 'give_access_error' => 'Para dar acceso a una sección debes desmarcar la opción Acceso Limitado',
 'select_all' => 'Seleccionar todo',
 'unauthorized_action' => 'No estas autorizado para realizar está acción',
 'unauthorized_section' => 'No tienes permiso para ver esta sección, ingresa a las secciones disponibles que se encuentrán en el menú izquierdo.',
 'block_countries' => 'Bloquear Países',
 'block_countries_info' => 'Selecciona los países en los que no desea que se muestre su perfil, no podrán ver su perfil en ninguna sección del sitio.',
 'super_admin' => 'Super Admin',
 'couple' => 'Pareja',
 'video_on_way' => 'Vídeo en camino...',
 'video_processed_info' => 'Tu video está siendo procesado, recibirá una notificación cuando esté listo.',
 'video_processed_successfully_post' => 'Tu video ha sido procesado con éxito (Post)',
 'video_processed_successfully_message' => 'Tu video ha sido procesado con éxito (Mensaje)',
);";
	 $fileLangES = 'resources/lang/es/general.php';
	 @file_put_contents($fileLangES, str_replace($findStringLang, $replaceLangES, file_get_contents($fileLangES)));

	 //============ Start v2.6
	 if (! Schema::hasColumn('admin_settings',
			 'type_announcement',
			 'referral_system',
			 'auto_approve_post'
		 )) {
					 Schema::table('admin_settings', function($table) {
						$table->char('type_announcement', 10)->default('primary');
						$table->enum('referral_system', ['on', 'off'])->default('off');
						$table->enum('auto_approve_post', ['on', 'off'])->default('on');
			 });
		 }

		 if (! Schema::hasColumn('updates', 'status')) {
			 Schema::table('updates', function($table) {
				 $table->char('status', 20)->default('active')->index();
			 });
		 }

		 Schema::table('notifications', function($table) {
				 $table->unsignedInteger('type')->change();
		 });

		 if (! Schema::hasColumn('users',
		 'permissions',
		 'blocked_countries'
		 )) {
			 Schema::table('users', function($table) {
				 $table->text('permissions');
				 $table->text('blocked_countries');
			 });
		 }

		 // Update permissions to Admin
		 if (Schema::hasColumn('users', 'permissions')) {
				 User::whereId(1)->update([
					 'permissions' => 'full_access'
				 ]);
		 }

		 // Add Percentage to table Deposits
		 if (! Schema::hasColumn('deposits', 'percentage_applied', 'transaction_fee')) {
						 Schema::table('deposits', function($table) {
							$table->string('percentage_applied', 50);
							$table->float('transaction_fee', 10, 2);
				 });
			 }

			 if (! Schema::hasColumn('media', 'encoded')) {
				 Schema::table('media', function($table) {
					 $table->enum('encoded', ['yes', 'no'])->default('no')->after('video')->index();
				 });
			 }

			 if (! Schema::hasColumn('messages', 'mode')) {
				 Schema::table('messages', function($table) {
					 $table->enum('mode', ['active', 'pending'])->default('active')->index();
				 });
			 }

			 if (! Schema::hasColumn('media_messages', 'encoded')) {
				 Schema::table('media_messages', function($table) {
					 $table->enum('encoded', ['yes', 'no'])->default('no')->index();
				 });
			 }


		 //=============== End Query v2.6 ====================================

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');
			\Artisan::call('queue:restart');

			return $upgradeDone;

		}//<<---- End Version 2.6 ----->>

		if ($version == '2.7') {

			//============ Starting moving files...
			$oldVersion = $this->settings->version;
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = true;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion  || ! $this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

		//============== Files Affected ================//
		$files = [
			'SendTwoFactorCode.php' => $NOTIFICATIONS,// v2.7
			'Kernel.php' => app_path('Http').$DS,// v2.7
			'Helper.php' => $APP,// v2.7
			'web.php' => $ROUTES,// v2.7

			//============ CONTROLLERS =================//
			'AddFundsController.php' => $CONTROLLERS,// v2.7
			'AdminController.php' => $CONTROLLERS,// v2.7
			'HomeController.php' => $CONTROLLERS,// v2.7
			'TwoFactorAuthController.php' => $CONTROLLERS,// v2.7
			'UpdatesController.php' => $CONTROLLERS,// v2.7
			'UserController.php' => $CONTROLLERS,// v2.7
			'MessagesController.php' => $CONTROLLERS,// v2.7
			'RegisterController.php' => $CONTROLLERS_AUTH,// v2.7
			'LoginController.php' => $CONTROLLERS_AUTH,// v2.7

			'UserCountry.php' => $MIDDLEWARE,// v2.7
			'Referred.php' => $MIDDLEWARE,// v2.7

			'EncodeVideo.php' => $JOBS,// v2.7
			'EncodeVideoMessages.php' => $JOBS,// v2.7

			'User.php' => $MODELS,// v2.7
			'Referrals.php' => $MODELS,// v2.7
			'TwoFactorCodes.php' => $MODELS,// v2.7

			'Functions.php' => $TRAITS,// v2.7
			'UserDelete.php' => $TRAITS,// v2.7

			//============ PUBLIC =================//
			'app-functions.js' => $PUBLIC_JS,// v2.7

			//=========== VIEWS ===================//
			'app.blade.php' => $VIEWS_LAYOUTS,// v2.7

			'navbar.blade.php' => $VIEWS_INCLUDES,// v2.7
			'messages-chat.blade.php' => $VIEWS_INCLUDES,// v2.7
			'css_general.blade.php' => $VIEWS_INCLUDES,// v2.7
			'javascript_general.blade.php' => $VIEWS_INCLUDES,// v2.7
			'media-post.blade.php' => $VIEWS_INCLUDES,// v2.7
			'media-messages.blade.php' => $VIEWS_INCLUDES,// v2.7
			'cards-settings.blade.php' => $VIEWS_INCLUDES,// v2.7
			'modal-2fa.blade.php' => $VIEWS_INCLUDES,// v2.7
			'modal-new-message.blade.php' => $VIEWS_INCLUDES,// v2.7
			'messages-inbox.blade.php' => $VIEWS_INCLUDES,// v2.7
			'updates.blade.php' => $VIEWS_INCLUDES,// v2.7

			'profile.blade.php' => $VIEWS_USERS,// v2.7
			'notifications.blade.php' => $VIEWS_USERS,// v2.7
			'wallet.blade.php' => $VIEWS_USERS,// v2.7
			'my_posts.blade.php' => $VIEWS_USERS,// v2.7
			'edit_my_page.blade.php' => $VIEWS_USERS,// v2.7
			'subscription.blade.php' => $VIEWS_USERS,// v2.7
			'referrals.blade.php' => $VIEWS_USERS,// v2.7
			'payout_method.blade.php' => $VIEWS_USERS,// v2.7
			'privacy_security.blade.php' => $VIEWS_USERS,// v2.7
			'delete_account.blade.php' => $VIEWS_USERS, // v2.7

			];

			$filesAdmin = [
			'posts.blade.php' => $VIEWS_ADMIN,// v2.7
			'payments-settings.blade.php' => $VIEWS_ADMIN,// v.2.7
			'transactions.blade.php' => $VIEWS_ADMIN,// v.2.7
			'edit-page.blade.php' => $VIEWS_ADMIN,// v.2.7
			'email-settings.blade.php' => $VIEWS_ADMIN,// v2.7
			'settings.blade.php' => $VIEWS_ADMIN,// v2.7
			'pwa.blade.php' => $VIEWS_ADMIN,// v2.7
		];

			// Files
			foreach ($files as $file => $root) {
				 $this->moveFile($path.$file, $root.$file, $copy);
			}

			// Files Admin
			foreach ($filesAdmin as $file => $root) {
				 $this->moveFile($pathAdmin.$file, $root.$file, $copy);
			}

			// Folder Console
			$filePathFolderConsole = $path.'Console';
			$pathFolderConsole = app_path('Console').$DS;

			if ($copy == false) {
				File::deleteDirectory($pathFolderConsole);
			}

			$this->moveDirectory($filePathFolderConsole, $pathFolderConsole, $copy);

			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

		 //============== Start Query v2.6 ====================================

		 // Replace String
		 $findStringLang = ');';

		 // Ennglish
		 $replaceLangEN    = "
		 // Version 2.7
		 'watermark_on_videos' => 'Watermark on videos',
		 'subscription_price' => 'Subscription price',
		 'referrals' => 'Referrals',
		 'referrals_desc' => 'Welcome to your referral panel. Share your link and earn :percentage% of your referrals first transaction, be it a subscription, send a tip or a PPV!',// Not remove :percentage
		 'referral_system' => 'Referral system',
		 'percentage_referred' => 'Percentage of profit for each referral',
		 'total_registered_users' => 'Total registered users',
		 'total_transactions' => 'Total transactions',
		 'earnings_total' => 'Total Earnings',
		 'no_transactions_yet' => 'No transactions yet',
		 'your_referral_link' => 'Your referral link is:',
		 'referral_system_disabled' => 'The Referral System is currently disabled',
		 'referrals_made' => 'One of your referrals has made a',
		 'transaction' => 'transaction',
		 'referral_commission_applied' => 'Referral commission was applied',
		 'security' => 'Security',
		 'two_step_auth' => 'Two-Step Authentication',
		 'two_step_auth_info' => 'A code will be sent to your email every time you log in',
		 'two_step_authentication_code' => 'Two-Step Authentication Code',
		 'your_code_is' => 'Your code is: :code', // Not remove :code
		 'enter_code' => 'Enter the code',
		 '2fa_title_modal' => 'We have sent you a code to your email',
		 'code_2fa_invalid' => 'The code you entered is invalid',
		 'resend_code' => 'Resend code?',
		 'resend_code_success' => 'We have sent you a new code to your email',
		 'please_enter_code' => 'Please enter the code',
		 'delete_account_alert' => 'Watch out! This will permanently delete your account, and all your files, subscriptions, etc, and you will not be able to enter the site again.',
		 'chats' => 'Chats',
		 'no_chats' => 'You don\'t have any chat',
		 'error_active_system_referrals' => 'You cannot activate the Referral System if your commission fee is equal to 0',
);";
		 $fileLangEN = 'resources/lang/en/general.php';
		 @file_put_contents($fileLangEN, str_replace($findStringLang, $replaceLangEN, file_get_contents($fileLangEN)));

	 // Español
	 $replaceLangES    = "
	 // Version 2.6
	 'watermark_on_videos' => 'Marca de agua en vídeos',
	 'subscription_price' => 'Precio de suscripción',
	 'referrals' => 'Referidos',
	 'referrals_desc' => 'Bienvenido a su panel de referencia. ¡Comparta su enlace y gane un :percentage% de la primera transacción de su referido, ya sea una suscripción, enviar una propina o un PPV!',  // Not remove :percentage
	 'referral_system' => 'Sistema de referidos',
	 'percentage_referred' => 'Porcentaje de ganancia por cada referido',
	 'total_registered_users' => 'Total de usuarios registrados',
	 'total_transactions' => 'Total de transacciones',
	 'earnings_total' => 'Ganacias totales',
	 'no_transactions_yet' => 'Aún no hay transacciones',
	 'your_referral_link' => 'Tu enlace de referencia es:',
	 'referral_system_disabled' => 'El Sistema de Referidos actualmente está deshabilitado',
	 'referrals_made' => 'Uno de tus referidos ha realizado una',
	 'transaction' => 'transacción',
	 'referral_commission_applied' => 'Se aplicó la comisión de referidos',
	 'security' => 'Seguridad',
	 'two_step_auth' => 'Autenticación de dos pasos',
	 'two_step_auth_info' => 'Se le enviará un código a su correo electrónico cada vez que inicie sesión',
	 'two_step_authentication_code' => 'Código de Autenticación de dos pasos',
	 'your_code_is' => 'Tu código es: :code', // Not remove :code
	 'enter_code' => 'Ingrese el código',
	 '2fa_title_modal' => 'Te hemos enviado un código a tu correo electrónico',
	 'code_2fa_invalid' => 'El código que has ingresado no es válido',
	 'resend_code' => '¿Reenviar código?',
	 'resend_code_success' => 'Le hemos enviado un nuevo código a su correo electrónico',
	 'please_enter_code' => 'Por favor ingresa el código',
	 'delete_account_alert' => '¡Cuidado! Esto eliminará permanentemente su cuenta., y todos sus archivos, suscripciones, etc, y no podrá ingresar de nuevo al sitio.',
	 'chats' => 'Conversaciones',
	 'no_chats' => 'No tienes ninguna conversación',
	 'error_active_system_referrals' => 'No puede activar el Sistema de Referidos si tu cuota de comisión es igual a 0',
);";
	 $fileLangES = 'resources/lang/es/general.php';
	 @file_put_contents($fileLangES, str_replace($findStringLang, $replaceLangES, file_get_contents($fileLangES)));

	 //============ Start Query SQL ====================================
	 if (! Schema::hasColumn('admin_settings',
			 'watermark_on_videos',
			 'percentage_referred'
		 )) {
					 Schema::table('admin_settings', function($table) {
					$table->enum('watermark_on_videos', ['on', 'off'])->default('on');
					$table->unsignedInteger('percentage_referred')->default(5);
			 });
		 }

		 if (! Schema::hasTable('referrals')) {
			 Schema::create('referrals', function($table)
						{
								$table->bigIncrements('id');
								$table->unsignedInteger('user_id')->index();
								$table->unsignedInteger('referred_by')->index();
								$table->float('earnings', 10, 2);
								$table->char('type', 25);
								$table->timestamps();
						});
					}

		if (! Schema::hasColumn('transactions', 'referred_commission')) {
			Schema::table('transactions', function($table) {
				$table->unsignedInteger('referred_commission');
			});
		}

		if (! Schema::hasTable('two_factor_codes')) {
			Schema::create('two_factor_codes', function($table)
					 {
							 $table->bigIncrements('id');
							 $table->unsignedInteger('user_id');
							 $table->string('code', 25);
							 $table->timestamps();
					 });
				 }

			 if (! Schema::hasColumn('users', 'two_factor_auth')) {
				 Schema::table('users', function($table) {
					 $table->enum('two_factor_auth', ['yes', 'no'])->default('no');
				 });
			 }

		 //=============== End Query SQL ====================================

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');
			\Artisan::call('queue:restart');

			return $upgradeDone;

		}//<<---- End Version 2.7 ----->>

		if ($version == '2.8') {

			//============ Starting moving files...
			$oldVersion = $this->settings->version;
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = true;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion  || ! $this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

		//============== Files Affected ================//
		$files = [
			'Helper.php' => $APP,// v2.8
			'SocialAccountService.php' => $APP,// v2.8

			//============ CONTROLLERS =================//
			'AdminController.php' => $CONTROLLERS,// v2.8
			'HomeController.php' => $CONTROLLERS,// v2.8
			'TwoFactorAuthController.php' => $CONTROLLERS,// v2.8
			'UpdatesController.php' => $CONTROLLERS,// v2.8
			'UserController.php' => $CONTROLLERS,// v2.8
			'MessagesController.php' => $CONTROLLERS,// v2.8
			'StripeController.php' => $CONTROLLERS,// v2.8
			'StripeWebHookController.php' => $CONTROLLERS,// v2.8
			'SubscriptionsController.php' => $CONTROLLERS,// v2.8
			'PaystackController.php' => $CONTROLLERS,// v2.8
			'CCBillController.php' => $CONTROLLERS,// v2.8
			'PayPalController.php' => $CONTROLLERS,// v2.8
			'PagesController.php' => $CONTROLLERS,// v2.8
			'UploadMediaController.php' => $CONTROLLERS,// v2.8
			'UploadMediaMessageController.php' => $CONTROLLERS,// v2.8

			'MassMessagesListener.php' => $LISTENERS,// v2.8
			'NewPostListener.php' => $LISTENERS,// v2.8
			'SubscriptionDisabledListener.php' => $LISTENERS,// v2.8

			'SubscriptionDisabledEvent.php' => $EVENTS,// v2.8

			'User.php' => $MODELS,// v2.8
			'ReferralTransactions.php' => $MODELS,// v2.8
			'Subscriptions.php' => $MODELS,// v2.8

			'SubscriptionDisabled.php' => $NOTIFICATIONS,// v2.8

			'EventServiceProvider.php' => $PROVIDERS,// v2.8

			'RebillWallet.php' => $JOBS,// v2.8

			'Functions.php' => $TRAITS,// v2.8
			'UserDelete.php' => $TRAITS,// v2.8

			//============ PUBLIC =================//
			'app-functions.js' => $PUBLIC_JS,// v2.8
			'core.min.js' => $PUBLIC_JS,// v2.8

			//=========== VIEWS ===================//
			'app.blade.php' => $VIEWS_LAYOUTS,// v2.8

			'categories.blade.php' => $VIEWS_INDEX,// v2.8
			'creators.blade.php' => $VIEWS_INDEX,// v2.8

			'css_general.blade.php' => $VIEWS_INCLUDES,// v2.8
			'modal-2fa.blade.php' => $VIEWS_INCLUDES,// v2.8
			'messages-inbox.blade.php' => $VIEWS_INCLUDES,// v2.8
			'updates.blade.php' => $VIEWS_INCLUDES,// v2.8
			'footer-tiny.blade.php' => $VIEWS_INCLUDES,// v2.8
			'footer.blade.php' => $VIEWS_INCLUDES,// v2.8
			'media-messages.blade.php' => $VIEWS_INCLUDES,// v2.8
			'media-post.blade.php' => $VIEWS_INCLUDES,// v2.8

			'profile.blade.php' => $VIEWS_USERS,// v2.8
			'notifications.blade.php' => $VIEWS_USERS,// v2.8
			'add_payment_card.blade.php' => $VIEWS_USERS,// v2.8
			'my_cards.blade.php' => $VIEWS_USERS,// v2.8
			'referrals.blade.php' => $VIEWS_USERS,// v2.8
			'my_subscribers.blade.php' => $VIEWS_USERS,// v2.8
			'my_subscriptions.blade.php' => $VIEWS_USERS,// v2.8
			'subscription.blade.php' => $VIEWS_USERS,// v2.8

			'show.blade.php' => $VIEWS_PAGES,// v2.8

			'payment.blade.php' => resource_path('views'.$DS.'vendor'.$DS.'cashier').$DS,// v2.8
			'receipt.blade.php' => resource_path('views'.$DS.'vendor'.$DS.'cashier').$DS,// v2.8
			];

			$filesAdmin = [
			'posts.blade.php' => $VIEWS_ADMIN,// v2.8
			'members.blade.php' => $VIEWS_ADMIN,// v2.8
			'transactions.blade.php' => $VIEWS_ADMIN,
			'edit-page.blade.php' => $VIEWS_ADMIN,// v2.8
			'add-page.blade.php' => $VIEWS_ADMIN,// v2.8
			'pages.blade.php' => $VIEWS_ADMIN,// v2.8
			'payments-settings.blade.php' => $VIEWS_ADMIN,// v2.8
			'settings.blade.php' => $VIEWS_ADMIN,// v2.8
			'reports.blade.php' => $VIEWS_ADMIN,// v2.8
		];

			// Files
			foreach ($files as $file => $root) {
				 $this->moveFile($path.$file, $root.$file, $copy);
			}

			// Files Admin
			foreach ($filesAdmin as $file => $root) {
				 $this->moveFile($pathAdmin.$file, $root.$file, $copy);
			}

			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

		 //============== Start Query ====================================

		 // Replace String
		 $findStringLang = ');';

		 // Ennglish
		 $replaceLangEN    = "
		 // Version 2.8
		 'encode' => 'Encode',
		 'your_amount_payment' => 'Your :amount payment', // Not remove :amount
		 'payment_processing' => 'Payment Processing',
		 'payment_processing_info' => 'This payment is currently processing. Refresh this page from time to time to see its status.',
		 'stripe_payment_info' => 'A valid payment method is needed to process your payment. Please confirm your payment by filling out your payment details below.',
		 'payment_method' => 'Payment Method',
		 'payment_method_info' => 'Please select the payment method which you\'d like to use.',
		 'processing' => 'Processing...',
		 'stripe_text_info_1' => 'Your payment will be processed by',
		 'stripe_text_info_2' => 'Payment details',
		 'stripe_text_info_3' => 'Remember payment method for future usage',
		 'stripe_text_info_4' => 'Confirm your :amount payment with', // Not remove :amount
		 'stripe_text_info_5' => 'Please provide your name and e-mail address.',
		 'reject' => 'Reject',
		 'resending_code' => 'Resending code...',
		 'referral_transaction_limit' => 'Limit of transactions by referrals',
 		'referrals_welcome_desc' => 'Welcome to your referral panel. Share your link and earn :percentage% of your referrals, be it a Subscription, Tip or a PPV!',// Not remove :percentage
 		'total_transactions_per_referral' => 'You will earn :percentage% for the first transaction of your referral|You will earn :percentage% for the first :total transactions of your referral', // Not remove :percentage and :total
 		'total_transactions_referral_unlimited' => 'You will earn :percentage% for each transaction of your referral',
 		'error_fee_commission_zero' => 'Your fee commission cannot be 0% if the Referral System is enabled',
		'payment_received_subscription_renewal' => 'payment received for subscription renewal',
		'page_lang' => 'Select the language that you will write this page',
		'default_language' => 'Default language',
		'default_language_info' => 'This language will be taken by default when the user language does not exist.',
		'slug_lang_info' => 'If this page is a translation of an existing page, put the Slug/Url of that page.',
		'video_encoding' => 'Video encoding',
		'video_encoding_alert' => 'You must have FFMPEG installed',
		'alert_disable_free_subscriptions' => 'If you have free subscribers, the subscriptions will be canceled.',
		'alert_disable_paid_subscriptions' => 'If you have paid subscribers, they will be notified that you have switched to free subscription. They will be able to cancel their subscription.',
		'has_changed_subscription_free_subject' => 'has changed their subscription to free.',
		'has_changed_subscription_free' => 'has changed their subscription to free, to cancel your current subscription click on the following button.',
		'has_changed_subscription_paid' => 'has changed your subscription to paid',
		'subscribe_now' => 'Subscribe now!',

);";
		 $fileLangEN = 'resources/lang/en/general.php';
		 @file_put_contents($fileLangEN, str_replace($findStringLang, $replaceLangEN, file_get_contents($fileLangEN)));

	 // Español
	 $replaceLangES    = "
	 // Version 2.8
	 'encode' => 'Codificar',
	 'your_amount_payment' => 'Su pago de :amount', // Not remove :amount
	 'payment_processing' => 'Procesando pago',
	 'payment_processing_info' => 'Este pago se está procesando actualmente. Actualice esta página de vez en cuando para ver su estado.',
	 'stripe_payment_info' => 'Se necesita un método de pago válido para procesar su pago. Confirme su pago completando los detalles de pago a continuación.',
	 'payment_method' => 'Método de pago',
	 'payment_method_info' => 'Seleccione el método de pago que le gustaría utilizar.',
	 'processing' => 'Procesando...',
	 'stripe_text_info_1' => 'Su pago será procesado por',
	 'stripe_text_info_2' => 'Detalles del pago',
	 'stripe_text_info_3' => 'Recuerde el método de pago para uso futuro',
	 'stripe_text_info_4' => 'Confirme su pago de :amount con', // Not remove :amount
	 'stripe_text_info_5' => 'Proporcione su nombre y dirección de correo electrónico.',
	 'reject' => 'Rechazar',
	 'resending_code' => 'Reenviando código...',
	 'referral_transaction_limit' => 'Límite de transacciones por referidos',
 	'referrals_welcome_desc' => 'Bienvenido a su panel de referencia. ¡Comparta su enlace y gane un :percentage% de su referido, ya sea una Suscripción, Propina o un PPV!', // Not remove :percentage
 	'total_transactions_per_referral' => 'Ganará el :percentage% por las primer transacción de su referido|Ganará el :percentage% por las primeras :total transacciones de su referido', // Not remove :percentage and :total
 	'total_transactions_referral_unlimited' => 'Ganará el :percentage% por cada transacción de su referido',
 	'error_fee_commission_zero' => 'La cuota de comisión no puede ser del 0% si el Sistema de Referencia está habilitado',
	'payment_received_subscription_renewal' => 'pago recibido por renovación de suscripción',
	'page_lang' => 'Seleccione el idioma en el que escribirá esta página',
	'default_language' => 'Lenguaje por defecto',
	'default_language_info' => 'Este lenguaje será tomado por defecto cuando el lenguaje del usuario no exista.',
	'slug_lang_info' => 'Si esta página es una traducción de una página existente, coloque el Slug/Url de esa página.',
	'encode_videos' => 'Codificación de videos',
	'video_encoding_alert' => 'Debe tener FFMPEG instalado',
	'alert_disable_free_subscriptions' => 'Si tienes suscriptores gratuitos, las suscripciones serán canceladas.',
	'alert_disable_paid_subscriptions' => 'Si tienes suscriptores de pago, se le notificará que has cambiado a suscripción gratuita. Podrán cancelar su suscripción.',
	'has_changed_subscription_free_subject' => 'ha cambiado su suscripción a gratuita.',
	'has_changed_subscription_free' => 'ha cambiado su suscripción a gratuita, para cancelar tu actual suscripción haz clic en el siguiente botón.',
	'has_changed_subscription_paid' => 'ha cambiado su suscripción a paga',
	'subscribe_now' => '¡Suscríbase ahora!',
);";
	 $fileLangES = 'resources/lang/es/general.php';
	 @file_put_contents($fileLangES, str_replace($findStringLang, $replaceLangES, file_get_contents($fileLangES)));

	 //============ Start Query SQL ====================================
		 Schema::table('subscriptions', function($table) {
				 $table->renameColumn('stripe_plan', 'stripe_price');
		 });

		 Schema::table('subscription_items', function($table) {
				 $table->renameColumn('stripe_plan', 'stripe_price');
		 });

		 Schema::table('users', function ($table) {
	    $table->renameColumn('card_brand', 'pm_type');
	    $table->renameColumn('card_last_four', 'pm_last_four');
		});

		Schema::table('subscription_items', function ($table) {
	    $table->string('stripe_product')->nullable()->after('stripe_id');
		});

		Schema::table('subscription_items', function ($table) {
	    $table->integer('quantity')->nullable()->change();
		});

		if (! Schema::hasColumn('admin_settings', 'referral_transaction_limit', 'conversion_ffmpeg')) {
 					 Schema::table('admin_settings', function($table) {
						 $table->char('referral_transaction_limit', 10)->default('1');
						 $table->enum('video_encoding', ['on', 'off'])->default('off');
 			 });
 		 }


			 if (! Schema::hasTable('referral_transactions')) {
				 Schema::create('referral_transactions', function($table)
							{
									$table->bigIncrements('id');
									$table->unsignedInteger('referrals_id')->index();
									$table->unsignedInteger('user_id')->index();
									$table->unsignedInteger('referred_by')->index();
									$table->float('earnings', 10, 2);
									$table->char('type', 25);
									$table->timestamps();
							});
						}

					 if (Schema::hasTable('referral_transactions')) {

						 $referrals = Referrals::where('type', '<>', '')->get();

						 foreach ($referrals as $ref) {
							 $data[] = [
								 'referrals_id' => $ref->id,
								 'user_id' => $ref->user_id,
								 'referred_by' => $ref->referred_by,
								 'earnings' => $ref->earnings,
								 'type' => $ref->type,
								 'created_at' =>  $ref->updated_at
							 ];
						 }

						 if (isset($data)) {
						 	 ReferralTransactions::insert($data);
						 }

						 Schema::table('referrals', function($table) {
							 $table->dropColumn('earnings');
							 $table->dropColumn('type');
					 });
				 }

				 if (! Schema::hasColumn('pages', 'lang')) {
		  					 Schema::table('pages', function($table) {
		 						 $table->char('lang', 10)->default(session('locale'));
		  			 });
		  		 }

					if (! Schema::hasColumn('failed_jobs', 'uuid')) {
					 Schema::table('failed_jobs', function ($table) {
						 $table->string('uuid')->after('id')->nullable()->unique();
					 });
				 }

					 file_put_contents(
			        '.env',
			        "\nDEFAULT_LOCALE=".session('locale')."\n",
			        FILE_APPEND
			    );

		 //=============== End Query SQL ====================================

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');
			\Artisan::call('queue:restart');

			return $upgradeDone;

		}//<<---- End Version 2.8 ----->>

		if ($version == '2.9') {

			//============ Starting moving files...
			$oldVersion = $this->settings->version;
			$path       = "v$version/";
			$pathAdmin  = "v$version/admin/";
			$copy       = false;

			if ($this->settings->version == $version) {
				return redirect('/');
			}

			if ($this->settings->version != $oldVersion  || ! $this->settings->version) {
				return "<h2 style='text-align:center; margin-top: 30px; font-family: Arial, san-serif;color: #ff0000;'>Error! you must update from version $oldVersion</h2>";
			}

		//============== Files Affected ================//
		$files = [
			'Helper.php' => $APP,// v2.9
			'web.php' => $ROUTES,// v2.9

			//============ CONTROLLERS =================//
			'AdminController.php' => $CONTROLLERS,// v2.9
			'HomeController.php' => $CONTROLLERS,// v2.9
			'LiveStreamingsController.php' => $CONTROLLERS,// v2.9
			'MessagesController.php' => $CONTROLLERS,// v2.9
			'TipController.php' => $CONTROLLERS,// v2.9
			'PagesController.php' => $CONTROLLERS,// v2.9
			'UpdatesController.php' => $CONTROLLERS,// v2.9
			'UserController.php' => $CONTROLLERS,// v2.9

			'LiveBroadcastingListener.php' => $LISTENERS,// v2.9

			'LiveBroadcasting.php' => $EVENTS,// v2.9

			'EventServiceProvider.php' => $PROVIDERS,// v2.9

			'User.php' => $MODELS,// v2.9
			'LiveComments.php' => $MODELS,// v2.9
			'LiveStreamings.php' => $MODELS,// v2.9
			'LiveOnlineUsers.php' => $MODELS,// v2.9
			'LiveLikes.php' => $MODELS,// v2.9

			'PreventRequestsDuringMaintenance.php' => $MIDDLEWARE,// v2.9
			'UserOnline.php' => $MIDDLEWARE,// v2.9
			'UserCountry.php' => $MIDDLEWARE,// v2.9
			'OnlineUsersLive.php' => $MIDDLEWARE,// v2.9

			//============ PUBLIC =================//
			'bootstrap-icons.css' => $PUBLIC_CSS,// v2.9
			'bootstrap-icons.woff' => $PUBLIC_FONTS,// v2.9
			'bootstrap-icons.woff2' => $PUBLIC_FONTS,// v2.9

			'app-functions.js' => $PUBLIC_JS,// v2.9
			'live.js' => $PUBLIC_JS,// v2.9
			'messages.js' => $PUBLIC_JS,// v2.9

			'live.png' => $PUBLIC_IMG, // v2.9

			//=========== VIEWS ===================//
			'app.blade.php' => $VIEWS_LAYOUTS,// v2.9

			'categories.blade.php' => $VIEWS_INDEX,// v2.9
			'creators.blade.php' => $VIEWS_INDEX,// v2.9
			'creators-live.blade.php' => $VIEWS_INDEX,// v2.9

			'cards-settings.blade.php' => $VIEWS_INCLUDES,// v2.9
			'css_general.blade.php' => $VIEWS_INCLUDES,// v2.9
			'comments-live.blade.php' => $VIEWS_INCLUDES,// v2.9
			'form-post.blade.php' => $VIEWS_INCLUDES,// v2.9
			'updates.blade.php' => $VIEWS_INCLUDES,// v2.9
			'footer.blade.php' => $VIEWS_INCLUDES,// v2.9
			'navbar.blade.php' => $VIEWS_INCLUDES,// v2.9
			'modal-login.blade.php' => $VIEWS_INCLUDES,// v2.9
			'modal-payperview.blade.php' => $VIEWS_INCLUDES,// v2.9
			'modal-pay-live.blade.php' => $VIEWS_INCLUDES,// v2.9
			'modal-live-stream.blade.php' => $VIEWS_INCLUDES,// v2.9
			'modal-tip.blade.php' => $VIEWS_INCLUDES,// v2.9
			'listing-creators-live.blade.php' => $VIEWS_INCLUDES,// v2.9

			'profile.blade.php' => $VIEWS_USERS,// v2.9
			'notifications.blade.php' => $VIEWS_USERS,// v2.9
			'privacy_security.blade.php' => $VIEWS_USERS,// v2.9
			'my_posts.blade.php' => $VIEWS_USERS,// v2.9
			'withdrawals.blade.php' => $VIEWS_USERS,// v2.9
			'edit_my_page.blade.php' => $VIEWS_USERS,// v2.9
			'live.blade.php' => $VIEWS_USERS,// v2.9

			'Kernel.php' => app_path('Http').$DS,// v2.9
			];

			$filesAdmin = [
			'edit-member.blade.php' => $VIEWS_ADMIN,// v2.9
			'live_streaming.blade.php' => $VIEWS_ADMIN,// v2.9
			'role-and-permissions-member.blade.php' => $VIEWS_ADMIN,// v2.9
			'layout.blade.php' => $VIEWS_ADMIN,// v2.9
			'profiles-social.blade.php' => $VIEWS_ADMIN,// v2.9
		];

			// Files
			foreach ($files as $file => $root) {
				 $this->moveFile($path.$file, $root.$file, $copy);
			}

			// Files Admin
			foreach ($filesAdmin as $file => $root) {
				 $this->moveFile($pathAdmin.$file, $root.$file, $copy);
			}

			// Agora Folder
			$filePathFolderAgora = $path.'agora';
			$pathFolderAgora = public_path('js'.$DS.'agora').$DS;

			$this->moveDirectory($filePathFolderAgora, $pathFolderAgora, $copy);

			// Copy UpgradeController
			if ($copy == true) {
				$this->moveFile($path.'UpgradeController.php', $CONTROLLERS.'UpgradeController.php', $copy);
		 }

		 //============== Start Query ====================================

		 // Replace String
		 $findStringLang = ');';

		 // Ennglish
		 $replaceLangEN    = "
		 // Version 2.9
		'live' => 'Live',
 		'live_streaming' => 'Live Streaming',
 		'live_streaming_min_price' => 'Minimum price Live Streaming',
 		'live_streaming_max_price' => 'Maximum price Live Streaming',
 		'stream_live' => 'Stream Live',
 		'create_live_stream' => 'Create Live Stream',
 		'create_live_stream_subtitle' => 'Start a live stream and interact with your subscribers.',
 		'info_price_live' => 'Price to be paid by free subscribers or non-subscribers.',
 		'chat' => 'Chat',
 		'welcome_live_room' => 'Welcome to my Live room!',
 		'info_offline_live' => 'I am currently not online, when I am online you will receive a notification.',
 		'info_offline_live_non_subscribe' => 'I am not currently online, but feel free to subscribe to receive notifications about my upcoming live streams.',
 		'end_live' => 'End Live Stream',
 		'has_joined' => 'has joined',
 		'you_have_joined' => 'you have joined',
 		'Join_live_stream' => 'Join Live Stream',
 		'already_payment_live_access' => 'You have already paid to access this live',
 		'confirm_end_live' => 'Are you sure you want to end the Live Stream?',
 		'yes_confirm_end_live' => 'Yes, finalize!',
 		'is_streaming_live' => 'is streaming live',
 		'go_live_stream' => 'Go to the live stream',
 		'tipped' => 'tipped',
 		'creators_live' => 'Creators Broadcasting live',
 		'join' => 'Join',
 		'no_live_streams' => 'There are no live streams at this time',
 		'exit_live_stream' => 'Exit Live Stream',
 		'withdrawal_pending' => 'You have a pending payment request.',
);";
		 $fileLangEN = 'resources/lang/en/general.php';
		 @file_put_contents($fileLangEN, str_replace($findStringLang, $replaceLangEN, file_get_contents($fileLangEN)));

	 // Español
	 $replaceLangES    = "
	 // Version 2.9
	'live' => 'Vivo',
 	'live_streaming' => 'Transmisión en vivo',
 	'live_streaming_min_price' => 'Precio mínimo Transmisión en vivo',
 	'live_streaming_max_price' => 'Precio máximo Transmisión en vivo',
 	'stream_live' => 'Transmitir en vivo',
 	'create_live_stream' => 'Crear transmisión en vivo',
 	'create_live_stream_subtitle' => 'Inicie una transmisión en vivo e interactúe con sus suscriptores.',
 	'info_price_live' => 'Precio que deben pagar suscriptores gratuitos o no suscriptores.',
 	'chat' => 'Chat',
 	'welcome_live_room' => '¡Bienvenido a mi sala en vivo!',
 	'info_offline_live' => 'Actualmente no estoy en línea, cuando esté en línea recibirás una notificación.',
 	'info_offline_live_non_subscribe' => 'Actualmente no estoy en línea, pero siéntete libre de suscribirte para recibir notificaciones sobre mis próximas transmisiones en vivo.',
 	'end_live' => 'Finalizar transmisión en vivo',
 	'has_joined' => 'se ha unido',
 	'you_have_joined' => 'te has unido',
 	'Join_live_stream' => 'Únete a la transmisión en vivo de',
 	'already_payment_live_access' => 'Ya pagó para acceder a este en vivo',
 	'confirm_end_live' => '¿Está seguro de que desea finalizar la transmisión en vivo?',
 	'yes_confirm_end_live' => '¡Sí, finalizar!',
 	'is_streaming_live' => 'está transmitiendo en vivo',
 	'go_live_stream' => 'Ir a la transmisión en vivo',
 	'tipped' => 'envío una propina de',
 	'creators_live' => 'Creadores Transmitiendo en vivo',
 	'join' => 'Únete',
 	'no_live_streams' => 'No hay transmisiones en vivo en este momento',
 	'exit_live_stream' => 'Salir de la transmisión en vivo',
 	'withdrawal_pending' => 'Tienes una solicitud de pago pendiente.',
);";
	 $fileLangES = 'resources/lang/es/general.php';
	 @file_put_contents($fileLangES, str_replace($findStringLang, $replaceLangES, file_get_contents($fileLangES)));

	 //============ Start Query SQL ====================================
		if (! Schema::hasColumn('admin_settings',
		'live_streaming_status',
		'live_streaming_minimum_price',
		'live_streaming_max_price',
		'agora_app_id',
		'tiktok',
		'snapchat',
	)) {
 					 Schema::table('admin_settings', function($table) {
						 $table->enum('live_streaming_status', ['on', 'off'])->default('off');
						 $table->unsignedInteger('live_streaming_minimum_price')->default(5);
						 $table->unsignedInteger('live_streaming_max_price')->default(100);
						 $table->string('agora_app_id', 200);
						 $table->string('tiktok', 200);
						 $table->string('snapchat', 200);
 			 });
 		 }

			 if (! Schema::hasTable('live_streamings')) {
				 Schema::create('live_streamings', function($table)
							{
									$table->bigIncrements('id');
									$table->unsignedInteger('user_id')->index();
									$table->string('name', 255);
									$table->text('channel');
									$table->unsignedInteger('price');
									$table->enum('status', ['0', '1'])->default(0);
									$table->timestamps();
							});
						}

				if (! Schema::hasTable('live_likes')) {
 				 Schema::create('live_likes', function($table)
 							{
 									$table->bigIncrements('id');
 									$table->unsignedInteger('user_id')->index();
 									$table->unsignedInteger('live_streamings_id')->index();
 									$table->timestamps();
 							});
 						}

				if (! Schema::hasTable('live_online_users')) {
 				 Schema::create('live_online_users', function($table)
 							{
 									$table->bigIncrements('id');
 									$table->unsignedInteger('user_id')->index();
 									$table->unsignedInteger('live_streamings_id')->index();
 									$table->timestamps();
 							});
 						}

					if (! Schema::hasTable('live_comments')) {
	 				 Schema::create('live_comments', function($table)
	 							{
	 									$table->bigIncrements('id');
	 									$table->unsignedInteger('user_id')->index();
	 									$table->unsignedInteger('live_streamings_id')->index();
										$table->text('comment')->collation('utf8mb4_unicode_ci');
										$table->unsignedInteger('joined')->default(1);
										$table->enum('tip', ['0', '1'])->default(0);
										$table->unsignedInteger('tip_amount');
	 									$table->timestamps();
	 							});
	 						}

					Schema::table('transactions', function($table) {
		 				 $table->string('type', 100)->change();
		 		 });

		 //=============== End Query SQL ====================================

			// Delete folder
			if ($copy == false) {
			 File::deleteDirectory("v$version");
		 }

			// Update Version
		 $this->settings->update([
					 'version' => $version
				 ]);

				 // Clear Cache, Config and Views
			\Artisan::call('cache:clear');
			\Artisan::call('config:clear');
			\Artisan::call('view:clear');
			\Artisan::call('queue:restart');

			return $upgradeDone;

		}//<<---- End Version 2.9 ----->>


	}//<--- End Method version
}
