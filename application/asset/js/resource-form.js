(function ($) {

    /**
     * Initialize the page.
     */
    var initPage = function() {
        if (typeof valuesJson == 'undefined') {
            // This is an add resource page.
            makeNewField('dcterms:title').find('.values')
                .append(makeDefaultValue('dcterms:title', 'literal'));
            makeNewField('dcterms:description').find('.values')
                .append(makeDefaultValue('dcterms:description', 'literal'));
        } else {
            // This is an edit resource page.
            $.each(valuesJson, function(term, valueObj) {
                var field = makeNewField(term);
                $.each(valueObj.values, function(index, value) {
                    field.find('.values').append(makeNewValue(term, value));
                });
            });
        }

        $.when(applyResourceTemplate()).done(function () {
            $('#properties').closest('form').trigger('o:form-loaded');
        });

        $('input.value-language').each(function() {
            var languageInput = $(this);
            if (languageInput.val() !== "") {
                languageInput.addClass('active');
                languageInput.prev('a.value-language').addClass('active');
            }
        });
    };

    /**
     * Make a default value.
     */
    var makeDefaultValue = function (term, type) {
        return makeNewValue(term, null, type)
            .addClass('default-value')
            .one('change', '*', function (event) {
                $(event.delegateTarget).removeClass('default-value');
            });
    };

    /**
     * Apply a resource template to the form.
     */
    var applyResourceTemplate = function() {

        // Fieldsets may have been marked as required in a previous state.
        $('.field').removeClass('required');

        var templateSelect = $('#resource-template-select');
        if (templateSelect.val()) {
            // A resource template is selected. Get its data type templates.
            var dataTypeTemplates = $('#data-type-templates')
                .children('[data-resource-template-id="' + templateSelect.val() + '"]');
            if (dataTypeTemplates.length) {
                // Data type templates already loaded.
                rewritePropertyFields();
            } else {
                // Load data type templates.
                $.get(templateSelect.data('data-type-templates-url'), {
                        resource_template_id: templateSelect.val(),
                        resource_id: $('#resource-values').data('resource-id'),
                    }).done(function(data) {
                        $('#data-type-templates').append(data);
                        rewritePropertyFields();
                    }).fail(function(data) {
                        console.log('Failed loading data type templates for the selected resource template.');
                    });
            }
        } else {
            // No resource template selected.
            var fields = $('#properties .resource-values');
            fields.find('div.single-selector').hide();
            fields.find('div.default-selector').show();
        }
    }

    /**
     * Rewrite all property fields according to the criteria defined by the
     * selected resource template. Called by applyResourceTemplate().
     */
    var rewritePropertyFields = function() {

        // Get the resource template data.
        var templateSelect = $('#resource-template-select');
        var data = $('#data-type-templates')
            .children('[data-resource-template-id="' + templateSelect.val() + '"]')
            .attr('data-resource-template-data');
        data = JSON.parse(data);

        // Change the resource class.
        var classSelect = $('#resource-class-select');
        if (data['o:resource_class'] && classSelect.val() === '') {
            classSelect.val(data['o:resource_class']['o:id']);
            classSelect.trigger('chosen:updated');
        }

        // Rewrite every property field defined by the template. We reverse the
        // order so property fields on page that are not defined by the template
        // are ultimately appended.
        var templatePropertyIds = data['o:resource_template_property']
            .reverse().map(function(templateProperty) {
                rewritePropertyField(templateProperty);
                return templateProperty['o:property']['o:id'];
            });

        // Property fields that are not defined by the template should use the
        // default selector.
        $('#properties .resource-values').each(function() {
            var propertyId = $(this).data('property-id');
            if (templatePropertyIds.indexOf(propertyId) === -1) {
                var field = $(this);
                field.find('div.single-selector').hide();
                field.find('div.default-selector').show();
            }
        });
    };

    /**
     * Rewrite one property field. Called by rewritePropertyFields().
     */
    var rewritePropertyField = function(templateProperty) {
        var properties = $('#properties');
        var propertyId = templateProperty['o:property']['o:id'];
        var field = properties.find('[data-property-id="' + propertyId + '"]');

        if (field.length == 0) {
            field = makeNewField(propertyId);
        }

        var singleSelector = field.find('.single-selector');
        var defaultSelector = field.find('.default-selector');
        var originalLabel = field.find('.field-label');
        var originalDescription = field.find('.field-description');

        if (templateProperty['o:data_type']) {
            // Use the single selector if the property has a data type.
            defaultSelector.hide();
            singleSelector.find('a.add-value.button').data('type', templateProperty['o:data_type'])
            singleSelector.show();

            // Remove any unchanged default values for this property so we start fresh.
            field.find('.value.default-value').remove();

            // Add an empty value if none already exist in the property.
            if (!field.find('.value').length) {
                field.find('.values').append(makeNewValue(
                    field.data('property-term'), null, templateProperty['o:data_type']
                ));
            }
        } else {
            // Use the default selector if the property has no data type.
            singleSelector.hide();
            defaultSelector.show();
        }

        if (templateProperty['o:alternate_label']) {
            originalLabel.clone()
                .addClass('alternate')
                .text(templateProperty['o:alternate_label'])
                .insertAfter(originalLabel)
            originalLabel.hide();
        }
        if (templateProperty['o:alternate_comment']) {
            originalDescription.clone()
                .addClass('alternate')
                .text(templateProperty['o:alternate_comment'])
                .insertAfter(originalDescription);
            originalDescription.hide();
        }
        if (templateProperty['o:is_required']) {
            field.addClass('required');
        }
        properties.prepend(field);
    };

    /**
     * Make a new property field.
     */
    var makeNewField = function(property) {
        //sort out whether property is the LI that holds data, or the id
        var propertyLi, propertyId;

        switch (typeof property) {
            case 'object':
                propertyLi = property;
                propertyId = propertyLi.data('property-id');
            break;

            case 'number':
                propertyId = property;
                propertyLi = $('#property-selector').find("li[data-property-id='" + propertyId + "']");
            break;

            case 'string':
                propertyLi = $('#property-selector').find("li[data-property-term='" + property + "']");
                propertyId = propertyLi.data('property-id');
            break;
        }

        var term = propertyLi.data('property-term');
        var field = $('.resource-values.field.template').clone(true);
        field.removeClass('template');
        field.find('.field-label').text(propertyLi.data('child-search')).attr('id', 'property-' + propertyId + '-label');
        field.find('.field-term').text(term);
        field.find('.field-description').prepend(propertyLi.find('.field-comment').text());
        field.data('property-term', term);
        field.data('property-id', propertyId);
        // Adding the attr because selectors need them to find the correct field
        // and count when adding more.
        field.attr('data-property-term', term);
        field.attr('data-property-id', propertyId);
        field.attr('aria-labelledby', 'property-' + propertyId + '-label');
        $('div#properties').append(field);
        return field;
    };

    /**
     * Prepare the markup for the resource data type.
     */
    var prepareResource = function(value, valueObj, namePrefix) {
        if (valueObj) {
            value.find('span.default').hide();
            var resource = value.find('.selected-resource');
            if (typeof valueObj['display_title'] === 'undefined') {
                valueObj['display_title'] = Omeka.jsTranslate('[Untitled]');
            }
            resource.find('.o-title')
                .removeClass() // remove all classes
                .addClass('o-title ' + valueObj['value_resource_name'])
                .html($('<a>', {href: valueObj['url'], text: valueObj['display_title']}));
            if (typeof valueObj['thumbnail_url'] !== 'undefined') {
                resource.find('.o-title')
                    .prepend($('<img>', {src: valueObj['thumbnail_url']}));
            }
        }
    }

    /**
     * Make a new value.
     */
    var makeNewValue = function(term, valueObj, type) {

        if (typeof type !== 'string') {
            type = valueObj['type'];
        }

        // Get the value node from the templates.
        var value;
        var templateValue;
        var templateSelect = $('#resource-template-select');
        if (templateSelect.val()) {
            // Get the data type markup defined by the resource template.
            templateValue = $('#data-type-templates')
                .children('[data-resource-template-id="' + templateSelect.val() + '"]')
                .children('.value')
                .filter('[data-data-type="' + type + '"]')
                .filter('[data-property-term="' + term + '"]');
            if (templateValue.length) {
                value = templateValue;
            }
        }
        if (!value) {
            // Get the defult data type markup.
            value = $('#data-type-templates')
                .children('.value')
                .filter('[data-data-type="' + type + '"]')
        }
        value = value.clone(true);
        value.removeClass('template');

        // Prepare the value node.
        var field = $('.resource-values.field[data-property-term="' + term + '"]');
        var count = field.find('.value').length;
        var namePrefix = field.data('property-term') + '[' + count + ']';
        var valueLabelID = 'property-' + field.data('property-id') + '-value-' + count + '-label';
        value.data('name-prefix', namePrefix);
        value.find('input.property')
            .attr('name', namePrefix + '[property_id]')
            .val(field.data('property-id'));
        value.find('input.type')
            .attr('name', namePrefix + '[type]')
            .val(type);
        value.find('span.label')
            .attr('id', valueLabelID);
        value.attr('aria-labelledby', valueLabelID);
        $(document).trigger('o:prepare-value', [type, value, valueObj, namePrefix]);

        return value;
    };

    $(document).ready(function() {

        // Select property
        $('#property-selector li.selector-child').on('click', function(e) {
            e.stopPropagation();
            var property = $(this);
            var term = property.data('property-term');
            var field = $('[data-property-term = "' + term + '"].field');
            if (!field.length) {
                field = makeNewField(property);
            }
            $('#property-selector').removeClass('mobile');
            Omeka.scrollTo(field);
        });

        $('#resource-template-select').on('change', function(e) {
            // Restore the original property label and comment.
            $('.alternate').remove();
            $('.field-label, .field-description').show();
            applyResourceTemplate();
        });

        $('a.value-language:not(.active)').on('click', function(e) {
            var button = $(this);
            e.preventDefault();
            button.next('input.value-language').addClass('active').focus();
            if (!button.hasClass('active')) {
                button.addClass('active');
            }
        });

        $('input.value-language').on('keyup', function(e) {
            var languageTag = this.value;
            // @see http://stackoverflow.com/questions/7035825/regular-expression-for-a-language-tag-as-defined-by-bcp47
            // Removes `|[A-Za-z]{4}|[A-Za-z]{5,8}` from the "language" portion
            // becuase, while in the spec, it does not represent current usage.
            if ('' == languageTag
                || languageTag.match(/^(((en-GB-oed|i-ami|i-bnn|i-default|i-enochian|i-hak|i-klingon|i-lux|i-mingo|i-navajo|i-pwn|i-tao|i-tay|i-tsu|sgn-BE-FR|sgn-BE-NL|sgn-CH-DE)|(art-lojban|cel-gaulish|no-bok|no-nyn|zh-guoyu|zh-hakka|zh-min|zh-min-nan|zh-xiang))|((([A-Za-z]{2,3}(-([A-Za-z]{3}(-[A-Za-z]{3}){0,2}))?))(-([A-Za-z]{4}))?(-([A-Za-z]{2}|[0-9]{3}))?(-([A-Za-z0-9]{5,8}|[0-9][A-Za-z0-9]{3}))*(-([0-9A-WY-Za-wy-z](-[A-Za-z0-9]{2,8})+))*(-(x(-[A-Za-z0-9]{1,8})+))?)|(x(-[A-Za-z0-9]{1,8})+))$/)) {
                this.setCustomValidity('');
            } else {
                this.setCustomValidity(Omeka.jsTranslate('Please enter a valid language tag'));
            }
        });

        // Make new value inputs whenever "add value" button clicked.
        $('#properties').on('click', '.add-value', function(e) {
            e.preventDefault();
            var typeButton = $(this);
            var field = typeButton.closest('.resource-values.field');
            var value = makeNewValue(field.data('property-term'), null, typeButton.data('type'))
            field.find('.values').append(value);
        });

        // Remove value.
        $('a.remove-value').on('click', function(e) {
            e.preventDefault();
            var thisButton = $(this);
            var value = thisButton.closest('.value');
            // Disable all form controls.
            value.find(':input').prop('disabled', true);
            value.addClass('delete');
            value.find('a.restore-value').show().focus();
            thisButton.hide();
        });

        // Restore a removed value
        $('a.restore-value').on('click', function(e) {
            e.preventDefault();
            var thisButton = $(this);
            var value = thisButton.closest('.value');
            // Enable all form controls.
            value.find('*').filter(':input').prop('disabled', false);
            value.removeClass('delete');
            value.find('a.remove-value').show().focus();
            thisButton.hide();
        });

        // Open or close item set
        $('a.o-icon-lock, a.o-icon-unlock').click(function(e) {
            e.preventDefault();
            var isOpenIcon = $(this);
            $(this).toggleClass('o-icon-lock').toggleClass('o-icon-unlock');
            var isOpenHiddenValue = $('input[name="o:is_open"]');
            if (isOpenHiddenValue.val() == 0) {
                isOpenIcon.attr('aria-label', Omeka.jsTranslate('Close item set'));
                isOpenIcon.attr('title', Omeka.jsTranslate('Close item set'));
                isOpenHiddenValue.attr('value', 1);
            } else {
                isOpenHiddenValue.attr('value', 0);
                isOpenIcon.attr('aria-label', Omeka.jsTranslate('Open item set'));
                isOpenIcon.attr('title', Omeka.jsTranslate('Open item set'));
            }
        });

        $('#select-item a').on('o:resource-selected', function (e) {
            var value = $('.value.selecting-resource');
            var valueObj = $('.resource-details').data('resource-values');
            var namePrefix = value.data('name-prefix');

            $(document).trigger('o:prepare-value', ['resource', value, valueObj, namePrefix]);
            Omeka.closeSidebar($('#select-resource'));
        });

        // Prevent resource details from opening when quick add is toggled on.
        $('#select-resource').on('click', '.quick-select-toggle', function() {
            $('#item-results').find('a.select-resource').each(function() {
                $(this).toggleClass('sidebar-content');
            });
        });

        $('#select-resource').on('o:resources-selected', '.select-resources-button', function(e) {
            var value = $('.value.selecting-resource');
            var field = value.closest('.resource-values.field');
            $('#item-results').find('.resource')
                .has('input.select-resource-checkbox:checked').each(function(index) {
                    if (0 < index) {
                        value = makeNewValue(field.data('property-term'), null, 'resource');
                        field.find('.values').append(value);
                    }
                    var valueObj = $(this).data('resource-values');
                    var namePrefix = value.data('name-prefix');
                    $(document).trigger('o:prepare-value', ['resource', value, valueObj, namePrefix]);
                });
        });

        $(document).on('click', '.button.resource-select', function(e) {
            e.preventDefault();
            var selectButton = $(this);
            var sidebar = $('#select-resource');
            var term = selectButton.closest('.resource-values').data('property-term');
            $('.selecting-resource').removeClass('selecting-resource');
            selectButton.closest('.value').addClass('selecting-resource');
            $('#select-item a').data('property-term', term);
            Omeka.populateSidebarContent(sidebar, selectButton.data('sidebar-content-url'));
            Omeka.openSidebar(sidebar);
        });

        // Prepare the markup for the default data types.
        $(document).on('o:prepare-value', function(e, type, value, valueObj, namePrefix) {
            // Prepare simple single-value form inputs using data-value-key
            value.find(':input').each(function () {
                valueKey = $(this).data('valueKey');
                if (!valueKey) {
                    return;
                }
                $(this).attr('name', namePrefix + '[' + valueKey + ']')
                    .val(valueObj ? valueObj[valueKey] : null);
            });

            if (type === 'resource') {
                prepareResource(value, valueObj, namePrefix);
            }
        });

        $('.visibility [type="checkbox"]').on('click', function() {
            var publicCheck = $(this);
            if (publicCheck.prop("checked")) {
                publicCheck.attr('checked','checked');
            } else {
                publicCheck.removeAttr('checked');
            }
        });

        // Handle validation for required properties.
        $('form.resource-form').on('submit', function(e) {

            var thisForm = $(this);
            var errors = [];

            // Iterate all required properties.
            var requiredProps = thisForm.find('.resource-values.required');
            requiredProps.each(function() {

                var thisProp = $(this);
                var propIsCompleted = false;

                // Iterate all values for this required property.
                var requiredValues = $(this).find('.value').not('.delete');
                requiredValues.each(function() {

                    var thisValue = $(this);
                    var valueIsCompleted = true;

                    // All inputs of this value with the "to-require" class must
                    // be completed when the property is required.
                    var toRequire = thisValue.find('.to-require');
                    toRequire.each(function() {
                        if ('' === $.trim($(this).val())) {
                            // Found an incomplete input.
                            valueIsCompleted = false;
                        }
                    });
                    if (valueIsCompleted) {
                        // There's at least one completed value of this required
                        // property. Consider the requirement satisfied.
                        propIsCompleted = true;
                        return false; // break out of each
                    }
                });
                if (!propIsCompleted) {
                    // No completed values found for this required property.
                    var propLabel = thisProp.find('.field-label').text();
                    errors.push('The following field is required: ' + propLabel);
                }
            });
            if (errors.length) {
                e.preventDefault();
                alert(errors.join("\n"));
            }
        });

        initPage();

    });

})(window.jQuery);
