var Exment;
(function (Exment) {
    class ModalEvent {
        /**
         * Call only once. It's $(document).on event.
         */
        static AddEventOnce() {
            $(document).off('click', '[data-widgetmodal_url]').on('click', '[data-widgetmodal_url]', {}, Exment.ModalEvent.setModalEvent);
            $(document).off('click', '#modal-showmodal .modal-body a').on('click', '#modal-showmodal .modal-body a', {}, Exment.ModalEvent.setLinkClickEvent);
            $(document).off('click', '#modal-showmodal .modal-submit').on('click', '#modal-showmodal .modal-submit', {}, Exment.ModalEvent.setSubmitEvent);
            Exment.ModalEvent.autoloadModalEvent();
            $('#modal-showmodal').on('hidden.bs.modal', function (e) {
                $('#modal-showmodal .modal-body').html('');
            });
        }
        static AddEvent() {
        }
        static ShowModal($target, url, params = []) {
            let original_title = $target.data('original-title');
            /// get data from "data-widgetmodal_getdata"
            let data = { targetid: $target.attr('id') };
            let getdataKeys = $target.data('widgetmodal_getdata');
            if (hasValue(getdataKeys)) {
                for (var key in getdataKeys) {
                    data[getdataKeys[key]] = $target.find('.' + getdataKeys[key]).val();
                }
            }
            // set uuid
            let uuid = $target.attr('data-widgetmodal_uuid');
            if (!hasValue(uuid)) {
                uuid = getUuid();
                $target.attr('data-widgetmodal_uuid', uuid);
            }
            data['widgetmodal_uuid'] = uuid;
            // get expand data
            let expand = $target.data('widgetmodal_expand');
            if (hasValue(expand)) {
                data = $.extend(data, expand);
            }
            let method = hasValue($target.data('widgetmodal_method')) ? $target.data('widgetmodal_method') : 'GET';
            if (method.toUpperCase() == 'POST') {
                data['_token'] = LA.token;
            }
            // if get index
            if (hasValue($target.data('widgetmodal_hasmany'))) {
                data['index'] = Exment.ModalEvent.getIndex($target);
            }
            data = $.extend(data, params);
            // get ajax
            $.ajax({
                url: url,
                method: method,
                data: data
            }).done(function (res) {
                $('#modal-showmodal button.modal-submit').removeClass('d-none');
                // change html
                Exment.ModalEvent.setBodyHtml(res, null, original_title);
                if (!$('#modal-showmodal').hasClass('in')) {
                    $('#modal-showmodal').modal('show');
                    Exment.CommonEvent.AddEvent();
                }
            }).fail(function (res, textStatus, errorThrown) {
            }).always(function (res) {
            });
        }
        /**
         * Showing static html
         */
        static ShowModalHtml($target, $html, title) {
            let original_title = $target.data('original-title');
            $('#modal-showmodal button.modal-submit').removeClass('d-none');
            // change html
            Exment.ModalEvent.setBodyHtml({ body: $html.html(), title: title, showSubmit: false }, null, original_title);
            if (!$('#modal-showmodal').hasClass('in')) {
                $('#modal-showmodal').modal('show');
                Exment.CommonEvent.AddEvent();
            }
        }
        /**
         * Open modal automatic
         */
        static autoloadModalEvent() {
            const target = $('[data-widgetmodal_autoload]');
            const url = target.data('widgetmodal_autoload');
            if (!hasValue(url)) {
                return;
            }
            Exment.ModalEvent.ShowModal(target, url);
        }
        static setMessagesOrErrors(messages, isError) {
            for (let key in messages) {
                var message = messages[key];
                var target = $('.modal .' + key);
                var parent = target.closest('.form-group');
                if (isError) {
                    parent.addClass('has-error');
                }
                // add message
                if (message.type == 'input') {
                    let m = message.message;
                    // set value
                    var base_message = (target.val().length > 0 ? target.val() + "\\r\\n" : '');
                    target.val(base_message + m).addClass('modal-input-area');
                }
                else {
                    let m = message;
                    parent.children('div').prepend($('<label/>', {
                        'class': 'control-label error-label',
                        'for': 'inputError',
                        'html': [
                            $('<i/>', {
                                'class': 'fa fa-times-circle-o'
                            }),
                            $('<span/>', {
                                'text': ' ' + m
                            }),
                        ]
                    }));
                }
            }
        }
        static setBodyHtml(res, button, original_title) {
            // change html
            if (res.body) {
                $('#modal-showmodal .modal-body').html(res.body);
                if (res.script) {
                    for (var script of res.script) {
                        eval(script);
                    }
                }
                if (res.title) {
                    $('#modal-showmodal .modal-title').html(res.title);
                }
                if (res.actionurl) {
                    $('#modal-showmodal .modal-action-url').val(res.actionurl);
                }
            }
            else {
                $('#modal-showmodal .modal-body').html(res);
                if (hasValue(original_title)) {
                    $('#modal-showmodal .modal-title').html(original_title);
                }
                $('#modal-showmodal button.modal-submit').addClass('d-none');
            }
            // set modal contentname
            $('#modal-showmodal').attr('data-contentname', hasValue(res.contentname) ? res.contentname : null);
            // set buttonname
            const closelabel = hasValue(res.closelabel) ? res.closelabel : $('#modal-showmodal .modal-close-defaultlabel').val();
            const submitlabel = res.submitlabel ? res.submitlabel : $('#modal-showmodal .modal-submit-defaultlabel').val();
            $('#modal-showmodal').find('.modal-close').text(closelabel);
            $('#modal-showmodal').find('.modal-submit').text(submitlabel);
            $('#modal-showmodal').find('.modal-reset').toggle(res.showReset === true);
            let showSubmit = true;
            if (res.showSubmit !== undefined) {
                showSubmit = res.showSubmit;
            }
            $('#modal-showmodal').find('.modal-submit').toggle(showSubmit);
            let modalSize = 'modal-lg';
            if (hasValue(res.modalSize)) {
                modalSize = res.modalSize;
            }
            $('.exment-modal-dialog').removeClass().addClass('exment-modal-dialog modal-dialog ' + modalSize);
            Exment.ModalEvent.enableSubmit(button);
        }
        static enableSubmit(button) {
            if (!hasValue(button)) {
                return;
            }
            button.removeAttr('disabled').removeClass('disabled').text(button.data('buttontext'));
        }
        /**
         * get row index. ignore hide row
         * NOW ONLY has-many-table
         * @param $target
         */
        static getIndex($target) {
            const $tr = $target.closest('tr');
            const $table = $target.closest('table');
            let count = 0;
            $table.find('tbody tr').each(function (index, element) {
                if ($(element).is(':hidden')) {
                    return;
                }
                if ($(element).is($tr)) {
                    return false;
                }
                count++;
            });
            return count;
        }
    }
    ModalEvent.setModalEvent = (ev) => {
        const target = $(ev.target).closest('[data-widgetmodal_url]');
        const url = target.data('widgetmodal_url');
        const isHtml = target.data('widgetmodal_html');
        if (isHtml) {
            // get target html
            let uuid = target.data('widgetmodal_uuid');
            let html = $('.widgetmodal_html[data-widgetmodal_html_target="' + uuid + '"]');
            if (!hasValue(html)) {
                return;
            }
            Exment.ModalEvent.ShowModalHtml(target, html, html.data('widgetmodal_title'));
            return;
        }
        if (!hasValue(url)) {
            return;
        }
        Exment.ModalEvent.ShowModal(target, url);
    };
    /**
     * Set Link click event in Modal
     */
    ModalEvent.setLinkClickEvent = (ev) => {
        let a = $(ev.target).closest('a');
        if (hasValue(a.data('widgetmodal_url'))) {
            return;
        }
        if (a.data('modalclose') === false) {
            return;
        }
        if (a.data('toggle') === 'tooltip') {
            return;
        }
        $('#modal-showmodal .modal-body').html('');
        $('#modal-showmodal').modal('hide');
    };
    /**
     * set modal submit event
     */
    ModalEvent.setSubmitEvent = (e) => {
        let formurl = $(e.target).parents('.modal-content').find('form').attr('action');
        let method = $(e.target).parents('.modal-content').find('form').attr('method');
        if (!formurl)
            return;
        e.preventDefault();
        let form = $('#modal-showmodal form').get()[0];
        if (!form.reportValidity()) {
            return;
        }
        // get button element
        let button = $(e.target).closest('button');
        button.data('buttontext', button.text());
        // add class and prop
        button.prop('disabled', 'disabled').addClass('disabled').text('loading...');
        // remove error message
        $('.modal').find('.has-error').removeClass('has-error');
        $('.modal').find('.error-label').remove();
        $('.modal').find('.modal-input-area').val('');
        // POST Ajax
        if (method == 'GET') {
            let formData = getParamFromArray($(form).serializeArray());
            $.pjax({ container: '#pjax-container', url: formurl + '?' + formData });
            $('.modal').modal('hide');
            Exment.ModalEvent.enableSubmit(button);
            return;
        }
        // Create FormData Object
        var formData = new FormData(form);
        $.ajax({
            url: formurl,
            method: 'POST',
            // data as FormData
            data: formData,
            // Ajax doesn't process data
            processData: false,
            // contentType is false
            contentType: false
        }).done(function (res) {
            if (hasValue(res.body)) {
                Exment.ModalEvent.setBodyHtml(res, button, null);
            }
            else {
                // reomve class and prop
                Exment.ModalEvent.enableSubmit(button);
                Exment.CommonEvent.CallbackExmentAjax(res);
            }
            if (hasValue(res.messages)) {
                ModalEvent.setMessagesOrErrors(res.messages, false);
            }
        }).fail(function (res, textStatus, errorThrown) {
            // reomve class and prop
            Exment.ModalEvent.enableSubmit(button);
            // if not have responseJSON, undefined error
            if (!hasValue(res.responseJSON)) {
                Exment.CommonEvent.UndefinedError();
                return;
            }
            // show toastr
            if (hasValue(res.responseJSON.toastr)) {
                toastr.error(res.responseJSON.toastr);
            }
            // show error message
            if (hasValue(res.responseJSON.errors)) {
                ModalEvent.setMessagesOrErrors(res.responseJSON.errors, true);
            }
            if (hasValue(res.responseJSON.messages)) {
                ModalEvent.setMessagesOrErrors(res.responseJSON.messages, false);
            }
        }).always(function (res) {
        });
        return false;
    };
    Exment.ModalEvent = ModalEvent;
})(Exment || (Exment = {}));
$(function () {
    Exment.ModalEvent.AddEvent();
    Exment.ModalEvent.AddEventOnce();
});
