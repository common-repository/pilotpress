tinymce.PluginManager.add('pilotpress', function(editor, url) 
                  {

                    editor.addButton('merge_fields', {
                        type: 'listbox',
                    
                        text: 'Merge Fields',
                        icon: false,
                        classes: 'fixed-width btn widget',
                        onselect: function(e) {
                            if (this.value() != "" ) 
                            {
                                editor.insertContent("[pilotpress_field name='"+this.value()+"']");
                            } 
                        },
                        values: 
                            pilotpress_tiny_mce_plugin_default_fields
                        ,
                        onPostRender: function() {
                            // Select the second item by default
                        }
                    });

                    editor.addButton('short_codes', {
                        type: 'listbox',

                        text: 'Short Codes',
                        icon: false,
                        classes: 'fixed-width btn widget',
                        onselect: function(e) {
                            if (this.value() != "")
                            {
                                if (this.value() == "pilotpress_sync_contact")
                                {
                                    editor.insertContent("["+this.value()+"]");
                                }
                                else
                                {
                                    editor.insertContent("[show_if "+this.value()+"]content[/show_if]");
                                }
                            }
                        },
                        values:
                            pilotpress_tiny_mce_plugin_shortcodes
                        ,
                        onPostRender: function() {

                        }
                    });
                });