{*
* 2007-2017 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2017 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{extends file="helpers/form/form.tpl"}

{block name="defaultForm"}
    {$smarty.block.parent}

    <div class="panel" id="fieldset_0">
        <div class="panel-heading">
            <i class="icon-cog"></i>    Acciones
        </div>
        <div class="panel-footer">
            <button type="button" value="1" id="configuration_form_submit_btn" name="submitCrvNetImport" class="btn btn-default pull-right">
                <i class="process-icon-save"></i> Importar
            </button>
        </div>
    </div>

    <div class="popup_importing hide">
        <div class="popup_uploading_table">
            <div class="popup_uploading_tablecell">
                <div class="popup_uploading_content">
                    <div class="import-wrap-title">
                        <h4 class="import-title">
                            {l s='Importar datos' mod='CRVNetImport'}
                        </h4>
                        <div id="basicUsageClock"></div>
                        <div class="clearfix"></div>
                    </div>
                    <div class="import-wapper-block-3">
                        <div class="import-wapper-all">
                            <div class="import-wapper-percent" style="width:0%;"></div>
                            <div class="noTrespassingOuterBarG">
                                <div class="noTrespassingAnimationG">
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                    <div class="noTrespassingBarLineG"></div>
                                </div>
                            </div>
                            <span class="running">0%</span>
                        </div>
                        <samp class="percentage_import">Iniciando...</samp>
                        <div class="alert alert-warning import-alert">
                            {l s='Estamos procesando la importación, por favor espere y sea paciente. No cierre el navegador web! Este proceso puede demorar algunos minutos (incluso algunas horas) dependiendo de la velocidad de su servidor y del tamaño de sus datos. Es posible que desee tomar una taza de café mientras espera si es demasiado largo.' mod='CRVNetImport'}
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>
        </div>
    </div>

    <script language="JavaScript">
        $(document).ready(function () {
            $("#configuration_form_submit_btn").click(function () {
                $('.popup_importing .import-wapper-percent').css('transition','all 3s ease 0s');
                $('.popup_importing .import-wapper-percent').css('width','0%');
                $('.popup_importing .running').html('0%');
                $('.popup_importing .percentage_import').html('<strong>Importando fichero XML...</strong>');
                $("input[name='submitCrvNetImport'").val(1);

                $('.popup_importing').addClass('show');
                $('.popup_uploading .upload-wapper-percent').css('transition','all 3s ease 0s');
                $('.popup_importing .import-wapper-percent').css('width','0%');
                ajaxPercentImport = setInterval(function(){ ajaxPercentageImport() }, 60000);

                ajaxSteps();
            });
        });

        function ajaxSteps(){
            $.ajax({
                url: $('#configuration_form').attr( 'action' ),
                data: $('#configuration_form').serialize(),
                type: 'post',
                dataType: 'json',
                success: function(json){
                    if (json.error > 0){
                        $('.popup_importing').addClass('hide');
                        setTimeout(function(){
                            $('.popup_importing').removeClass('show');
                        }, 1000);
                        clearInterval(ajaxPercentImport);
                        alert(json.text);
                    }else{
                        if (parseInt($("input[name='submitCrvNetImport'").val()) < 5){
                            $("input[name='submitCrvNetImport'").val(parseInt($("input[name='submitCrvNetImport'").val()) +1);
                        }
                        ajaxSteps();
                    }
                },
                error: function(xhr, status, error)
                {
                    $('.popup_importing').addClass('hide');
                    setTimeout(function(){
                        $('.popup_importing').removeClass('show');
                    }, 1000);
                    clearInterval(ajaxPercentImport);
                    alert("Ha ocurrido un error al importar: " + error);
                }
            });
        }

        function  ajaxPercentageImport()
        {
            $.ajax({
                url: '',
                data: 'submitCrvNetImport=6',
                type: 'post',
                dataType: 'json',
                success: function(json){
                    if(!json)
                        return false;

                    if(json.percent>0 && json.percent<=100)
                    {
                        $('.popup_importing .import-wapper-percent').css('transition','all 3s ease 0s');
                        $('.popup_importing .import-wapper-percent').css('width',json.percent+'%');
                        $('.popup_importing .running').html(json.percent+'%');
                    }
                    $('.popup_importing .percentage_import').html('<strong>'+json.text+'</strong>');
                },
                error: function(xhr, status, error)
                {
                }
            });
        }
    </script>
{/block}