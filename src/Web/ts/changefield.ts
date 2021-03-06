declare var toastr: any;
declare var math: any;
declare var LA: any;
declare var BigNumber: any;
declare function swal(...x:any): any;

namespace Exment {
    export class ChangeFieldEvent {
        /**
         * toggle right-top help link and color
         */
        public static ChangeFieldEvent(ajax, eventTriggerSelector, eventTargetSelector, replaceSearch, replaceWord, showConditionKey, $hasManyTableClass){
            if(!hasValue(ajax)){
                return;
            }
            if(!hasValue($hasManyTableClass)){
                $hasManyTableClass = 'has-many-table';
            }

            $('.' + $hasManyTableClass).off('change').on('change', eventTriggerSelector, function (ev) {
                var changeTd = $(ev.target).closest('tr').find('.changefield-div');
                if(!hasValue($(ev.target).val())){
                    changeTd.html('');
                    return;
                }
                $.ajax({
                    url: ajax,
                    type: "GET",
                    data: {
                        'target': $(this).closest('tr').find(eventTargetSelector).val(),
                        'cond_name': $(this).attr('name'),
                        'cond_key': $(this).val(),
                        'replace_search': replaceSearch,
                        'replace_word': replaceWord,
                        'show_condition_key': showConditionKey,
                    },
                    context: this,
                    success: function (data) {
                        var json = JSON.parse(data);
                        $(this).closest('tr').find('.changefield-div').html(json.html);
                        if (json.script) {
                            eval(json.script);
                        }
                    },
                });
            });
        }
    }
}