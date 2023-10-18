Espo.define('esignature-field:views/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
        },

        setupActionItems: function (isPrototype = false) {

            if (!isPrototype) {
                // if this view is not being used as prototype to another view
                // add all the dropdown items called by the prototype view
                Dep.prototype.setupActionItems.call(this);
            }

            this.dropdownItemList.push({
                name: 'displayEsignatureDocument',
                label: 'Display eSignature Document'
            });
        },

        actionDisplayEsignatureDocument: function () {
            // get the document's template id if saved as a model field
            if (this.model.attributes.templateId) {
                var templateId = this.model.attributes.templateId;
                var options = {
                    entityType: this.model.name,
                    entityId: this.model.id,
                    templateId: templateId,
                    model: this.model
                };
                this.getRouter().navigate("#EsignatureDocument/showDocument/options");
                this.getRouter().dispatch("EsignatureDocument", 'showDocument', options);
                // if the template is not pre-determined, open modal to choose one
            } else {
                this.createView('pdfTemplate', 'views/modals/select-template', {
                    entityType: this.model.name
                }, function (view) {
                    view.render();
                    this.listenToOnce(view, 'select', function (model) {
                        this.clearView('pdfTemplate');
                        var templateId = model.id;
                        var options = {
                            entityType: this.model.name,
                            entityId: this.model.id,
                            templateId: templateId,
                            model: this.model
                        };
                        this.getRouter().navigate("#EsignatureDocument/showDocument/options");
                        this.getRouter().dispatch("EsignatureDocument", 'showDocument', options);
                    }, this);
                });
            }
        }
    });
});
