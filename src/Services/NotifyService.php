<?php
namespace Exceedone\Exment\Services;

use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\Notify;
use Exceedone\Exment\Model\NotifyTarget;
use Exceedone\Exment\Model\NotifyNavbar;
use Exceedone\Exment\Enums\NotifyAction;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\PluginEventTrigger;
use Exceedone\Exment\Model\File as ExmentFile;
use Exceedone\Exment\Form\Widgets\ModalForm;
use Exceedone\Exment\Notifications;

/**
 * Notify dialog, send mail etc.
 */
class NotifyService
{
    protected $notify;
    
    protected $targetid;

    protected $custom_table;
    
    protected $custom_value;

    public function __construct(Notify $notify, $targetid, $tableKey, $id)
    {
        $this->notify = $notify;
        $this->targetid = $targetid;

        $this->custom_table = CustomTable::getEloquent($tableKey);
        $this->custom_value = isset($this->custom_table) ? $this->custom_table->getValueModel($id) : null;
    }

    /**
     * Get dialog form for send mail
     *
     * @param Notify $notify
     * @return void
     */
    public function getNotifyDialogForm()
    {
        // get target users
        $users = $this->notify->getNotifyTargetUsers($this->custom_value)->filter();

        // if only one data, get form for detail
        if (count($users) <= 1) {
            return $this->getSendForm($users);
        }
        
        // create form fields
        $tableKey = $this->custom_table->table_name;
        $id = $this->custom_value->id;

        $form = new ModalForm();
        $form->disableReset();
        $form->disableSubmit();
        $form->modalAttribute('id', 'data_notify_modal');
        $form->modalHeader(exmtrans('custom_value.sendmail.title'));
        $form->action(admin_urls('data', $tableKey, $id, 'sendTargetUsers'));

        // progress tracker
        $form->progressTracker()->options($this->getProgressInfo(true));

        $options = [];
        foreach ($users as $user) {
            $options[$user->notifyKey()] = $user->getLabel();
        }

        // select target users
        $form->listbox('target_users', exmtrans('custom_value.sendmail.mail_to'))
            ->options($options)
            ->required()
            ->help(exmtrans('common.bootstrap_duallistbox_container.help'))
            ->settings(['nonSelectedListLabel' => exmtrans('common.bootstrap_duallistbox_container.nonSelectedListLabel'), 'selectedListLabel' => exmtrans('common.bootstrap_duallistbox_container.selectedListLabel')])
            ->setWidth(9, 2);

        $form->hidden('mail_template_id')->default($this->targetid);

        return $form;
    }

    /**
     *
     *
     * @param Notify $notify
     * @return void
     */
    public function getNotifyDialogFormMultiple($target_users)
    {
        $users = [];
        foreach ($target_users as $target_user) {
            // get definition target users
            if (!is_null($user = NotifyTarget::getSelectedNotifyTarget($target_user, $this->notify, $this->custom_value))) {
                $users[] = $user;
            }
        }

        return $this->getSendForm($users, true);
    }

    /**
     * Get Send Form. if only one user, Replace format.
     *
     * @return ModalForm
     */
    protected function getSendForm($notifyTargets, $isFlow = false)
    {
        $tableKey = $this->custom_table->table_name;
        $id = $this->custom_value->id;

        $mail_template = $this->notify->getMailTemplate();
        if (!isset($mail_template)) {
            abort(404);
        }

        $replace = count($notifyTargets) == 1;
        $mail_subject = array_get($mail_template->value, 'mail_subject');
        $mail_body = $mail_template->getJoinedBody();

        $notifyTarget = implode(exmtrans("common.separate_word"), collect($notifyTargets)->map(function ($notifyTarget) {
            return $notifyTarget->getLabel();
        })->toArray());
        $notifyTargetJson = json_encode(collect($notifyTargets)->map(function ($notifyTarget) {
            return $notifyTarget->notifyKey();
        })->toArray());

        if ($replace) {
            $mail_subject = replaceTextFromFormat($mail_subject, $this->custom_value);
            $mail_body = replaceTextFromFormat($mail_body, $this->custom_value);
        }
        
        // create form fields
        $form = new ModalForm();
        $form->disableReset();
        $form->disableSubmit();
        $form->modalAttribute('id', 'data_notify_modal');
        $form->modalHeader(exmtrans('custom_value.sendmail.title'));
        $form->action(admin_urls('data', $tableKey, $this->custom_value->id, 'sendMail'));

        if ($isFlow) {
            // progress tracker
            $form->progressTracker()->options($this->getProgressInfo(false));
        }

        if (in_array(NotifyAction::EMAIL, $this->notify->notify_actions)) {
            $form->display(exmtrans('custom_value.sendmail.mail_to'))->default($notifyTarget);
        }
        $form->hidden('target_users')->default($notifyTargetJson);

        $form->text('mail_title', exmtrans('custom_value.sendmail.mail_title'))
            ->default($mail_subject)
            ->required();

        $form->textarea('mail_message', exmtrans('custom_value.sendmail.mail_message'))
            ->default($mail_body)
            ->required()
            ->rows(10);

        $options = ExmentFile::where('parent_type', $tableKey)
            ->where('parent_id', $id)->get()->pluck('filename', 'uuid');

        if (in_array(NotifyAction::EMAIL, $this->notify->notify_actions)) {
            $form->multipleSelect('mail_attachment', exmtrans('custom_value.sendmail.attachment'))
                ->options($options);
        }

        $form->textarea('send_error_message', exmtrans('custom_value.sendmail.send_error_message'))
            ->attribute(['readonly' => true, 'placeholder' => ''])
            ->rows(1)
            ->addElementClass('send_error_message');

        $form->hidden('mail_key_name')->default(array_get($mail_template->value, 'mail_key_name'));
        $form->hidden('mail_template_id')->default($this->targetid);

        $form->setWidth(8, 3);

        return $form;
    }

    /**
     * send notfy mail
     *
     * @return void
     */
    public function sendNotifyMail($custom_table)
    {
        $request = request();

        $title = $request->get('mail_title');
        $message = $request->get('mail_message');
        $attachments = $request->get('mail_attachment');
        $mail_key_name = $request->get('mail_key_name');
        $mail_template_id = $request->get('mail_template_id');

        // get target users
        $target_user_keys = json_decode($request->get('target_users'), true);
        
        if (!isset($mail_key_name) || !isset($mail_template_id)) {
            abort(404);
        }

        $errors = [];

        if (isset($title) && isset($message)) {
            try {
                $this->notify->notifyButtonClick($this->custom_value, $target_user_keys, $title, $message, $attachments);
            } catch (\Swift_RfcComplianceException $ex) {
                return getAjaxResponse([
                    'result'  => false,
                    'errors' => ['send_error_message' => ['type' => 'input',
                        'message' => exmtrans('error.mailsend_failed')
                    ]],
                ]);
            } catch (\Exception $ex) {
                return getAjaxResponse([
                    'result'  => false,
                    'errors' => ['send_error_message' => ['type' => 'input',
                        'message' => exmtrans('error.mailsend_failed')
                    ]],
                ]);
            }
            return getAjaxResponse([
                'result'  => true,
                'toastr' => exmtrans('custom_value.sendmail.message.send_succeeded'),
            ]);
        } else {
            return getAjaxResponse([
                'result'  => false,
                'errors' => ['send_error_message' => ['type' => 'input',
                    'message' => exmtrans('custom_value.sendmail.message.empty_error')]],
            ]);
        }
    }
    
    /**
     * Execute Notify test
     *
     * @param array $params
     * @return void
     */
    public static function executeTestNotify($params = [])
    {
        $params = array_merge(
            [
                'to' => null,
                'type' => 'mail',
                'subject' => 'Exment TestMail',
                'body' => 'Exment TestMail'
            ],
            $params
        );
        $to = $params['to'];
        $type = $params['type'];
        $subject = $params['subject'];
        $body = $params['body'];


        // send mail
        try {
            Notifications\MailSender::make(null, $to)
                ->subject($subject)
                ->body($body)
                ->send();
        }
        // throw mailsend Exception
        catch (\Swift_TransportException $ex) {
            throw $ex;
        }
    }
    
    /**
     * Execute Notify action
     *
     * @param [type] $notify
     * @param array $params
     * @return void
     */
    public static function executeNotifyAction($notify, $params = [])
    {
        $params = array_merge(
            [
                'mail_template' => null,
                'prms' => [],
                'user' => null,
                'custom_value' => null,
                'subject' => null,
                'body' => null,
                'attach_files' => null,
                'is_chat' => false,
                'replaceOptions' => []
            ],
            $params
        );
        $mail_template = $params['mail_template'];
        $prms = $params['prms'];
        $user = $params['user'];
        $custom_value = $params['custom_value'];
        $subject = $params['subject'];
        $body = $params['body'];
        $attach_files = $params['attach_files'];
        $is_chat = $params['is_chat'];
        $replaceOptions = $params['replaceOptions'];


        // get template
        if (!isset($mail_template)) {
            $mail_template = array_get($notify->action_settings, 'mail_template_id');
        }
        if (is_numeric($mail_template)) {
            $mail_template = getModelName(SystemTableName::MAIL_TEMPLATE)::find($mail_template);
        }

        $subject = $subject ?? array_get($mail_template->value, 'mail_subject');
        $body = $body ?? array_get($mail_template->value, 'mail_body');

        // get notify actions
        $notify_actions = $notify->notify_actions;
        foreach ($notify_actions as $notify_action) {
            if (NotifyAction::isChatMessage($notify_action) != $is_chat) {
                continue;
            }
            Plugin::pluginExecuteEvent(PluginEventTrigger::NOTIFY_EXECUTING, $custom_value->custom_table, [
                'custom_table' => $custom_value->custom_table,
                'custom_value' => $custom_value,
                'notify' => $notify,
            ]);
            switch ($notify_action) {
                case NotifyAction::EMAIL:
                    // send mail
                    try {
                        Notifications\MailSender::make($mail_template, $user)
                        ->prms($prms)
                        ->user($user)
                        ->custom_value($custom_value)
                        ->subject($subject)
                        ->body($body)
                        ->attachments($attach_files)
                        ->replaceOptions($replaceOptions)
                        ->send();
                    }
                    // throw mailsend Exception
                    catch (\Swift_TransportException $ex) {
                        throw $ex;
                    }
                    break;

                case NotifyAction::SHOW_PAGE:
                    if ($user instanceof CustomValue) {
                        $id = $user->getUserId();
                    } elseif ($user instanceof NotifyTarget) {
                        $id = $user->id();
                    }

                    if (!isset($id)) {
                        break;
                    }

                    // save data
                    $login_user = \Exment::user();

                    // replace system:site_name to custom_value label
                    array_set($prms, 'system.site_name', $custom_value->label);
            
                    // replace value
                    $mail_subject = static::replaceWord($subject, $custom_value, $prms, $replaceOptions);
                    $mail_body = static::replaceWord($body, $custom_value, $prms, $replaceOptions);

                    $notify_navbar = new NotifyNavbar;
                    $notify_navbar->notify_id = array_get($notify, 'id');
                    $notify_navbar->parent_id = array_get($custom_value, 'id');
                    $notify_navbar->parent_type = $custom_value->custom_table->table_name;
                    $notify_navbar->notify_subject = $mail_subject;
                    $notify_navbar->notify_body = $mail_body;
                    $notify_navbar->target_user_id = $id;
                    $notify_navbar->trigger_user_id = isset($login_user) ? $login_user->getUserId() : null;
                    $notify_navbar->save();

                    break;

                case NotifyAction::SLACK:
                    // replace word
                    $slack_subject = static::replaceWord($subject, $custom_value, $prms, $replaceOptions);
                    $slack_body = static::replaceWord($body, $custom_value, $prms, $replaceOptions);
                    // send slack message
                    (new Notifications\SlackSender($slack_subject, $slack_body))->send($notify);
                    break;
    
                case NotifyAction::MICROSOFT_TEAMS:
                    // replace word
                    $slack_subject = static::replaceWord($subject, $custom_value, $prms, $replaceOptions);
                    $slack_body = static::replaceWord($body, $custom_value, $prms, $replaceOptions);
                    // send message
                    (new Notifications\MicrosoftTeamsSender($slack_subject, $slack_body))->send($notify);
                    break;
            }
            Plugin::pluginExecuteEvent(PluginEventTrigger::NOTIFY_EXECUTED, $custom_value->custom_table, [
                'custom_table' => $custom_value->custom_table,
                'custom_value' => $custom_value,
                'notify' => $notify,
            ]);
        }
    }

    /**
     * get Progress Info
     *
     * @param [type] $isSelectTarget
     * @return array
     */
    protected function getProgressInfo($isSelectTarget)
    {
        $steps[] = [
            'active' => $isSelectTarget,
            'complete' => false,
            'url' => null,
            'description' => exmtrans('notify.notify_select')
        ];
        $steps[] = [
            'active' => !$isSelectTarget,
            'complete' => false,
            'url' => null,
            'description' => exmtrans('notify.message_input')
        ];
        return $steps;
    }



    /**
     * replace subject or body words.
     */
    public static function replaceWord($target, $custom_value = null, $prms = null, $replaceOptions = [])
    {
        $replaceOptions = array_merge([
            'matchBeforeCallback' => function ($length_array, $matchKey, $format, $custom_value, $options) use ($prms) {
                // if has prms using $match, return value
                $matchKey = str_replace(":", ".", $matchKey);
                if (isset($prms) && array_has($prms, $matchKey)) {
                    return array_get($prms, $matchKey);
                }
                return null;
            }
        ], (array)$replaceOptions);

        $target = replaceTextFromFormat($target, $custom_value, $replaceOptions);

        return $target;
    }
}
