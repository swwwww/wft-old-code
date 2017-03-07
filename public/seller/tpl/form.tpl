<form class="modal-form"{{if action}}action="{{action}}"{{/if}} h-com="select-auto"{{if method}} method="{{method}}"{{/if}}>
    <div class="close modal-close"></div>
    {{if title}}
    <header class="modal-form-title">{{title}}</header>
    {{/if}}
    {{each fields field}}
    {{switch field.type}}
    {{case 'text'}}
    <fieldset>
        <label class="mf-label">{{field.label}}</label>
        <input class="mf-text text" type="text" name="{{field.name}}" value="{{field.value}}" placeholder="{{field.placeholder}}" />
    </fieldset>
    {{case 'hidden'}}
    <input type="hidden" name="{{field.name}}" value="{{field.value}}" />
    {{case 'textarea'}}
    <fieldset>
        <label class="mf-label">{{field.label}}</label>
        <textarea class="mf-textarea text" name="{{field.name}}" placeholder="{{field.placeholder}}">{{field.value}}</textarea>
    </fieldset>
    {{case 'select'}}
    <fieldset>
        <label class="mf-label">{{field.label}}</label>
        <div class="mf-select ui-select">
            <input type="hidden" name="{{field.name}}" value="{{field.value}}" >
            <div class="ui-select-title">类型</div>
            <ul class="ui-select-options">
                {{each field.data option}}
                <li data-value="{{option.value}}"{{if field.value && option.value == field.value}} class="selected"{{/if}}>{{option.title}}</li>
                {{/each}}
            </ul>
        </div>
    </fieldset>
    {{case 'img'}}
    <fieldset>
        <label class="mf-label">{{field.label}}</label>
        <div class="gf-file" h-com="upload-image" data-upload='{"url": "/public/upload","file_data_name": "file","type":"images","multi_selection":false}'>
            <input class="button gf-button upload-button" type="button" value="点击上传" tabindex="1" />
            <input class="upload-field" type="hidden" name="{{field.name}}" value="{{field.value}}" />
            <a class="upload-preview{{if field.value}} shown{{/if}}" href="/marry/uploads/{{field.value}}" target="_blank">
                <div class="upload-preview-container">
                    <img class="upload-preview-img" src="/marry/uploads/{{field.value}}" />
                </div>
                <div class="upload-preview-progress">
                    <div class="upg-info"></div>
                    <div class="upg-bar"></div>
                </div>
            </a>
        </div>
    </fieldset>
    {{/switch}}
    {{/each}}
    <div class="modal-form-error"></div>
    <footer class="modal-form-footer">
        <input class="button modal-form-submit" type="button" value="提交" />
        <input class="button modal-close minor" type="button" value="取消" />
    </footer>
</form>