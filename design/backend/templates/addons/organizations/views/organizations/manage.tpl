
{* @var \Tygh\Addons\Organizations\Organization[] $organizations *}
{* @var array $search *}

{capture name="mainbox"}
    {include file="common/pagination.tpl" search=$search}

    {$return_current_url = $config.current_url|escape:url}
    {$c_url = $config.current_url|fn_query_remove:"sort_by":"sort_order"}

    {if $organizations}
        <table class="table table-middle">
            <thead>
                <tr>
                    <th>{__("id")}</th>
                    <th>{__("name")}</th>
                    <th>{__("owner")}</th>
                    <th></th>
                    <th>{__("status")}</th>
                </tr>
            </thead>
            <tbody>
            {foreach $organizations as $organization}
                <tr>
                    <td>
                        <a href="{"organizations.update?organization_id={$organization->getOrganizationId()}"|fn_url}">
                            {$organization->getOrganizationId()}
                        </a>
                    </td>
                    <td>
                        <a href="{"organizations.update?organization_id={$organization->getOrganizationId()}"|fn_url}">
                            {$organization->getName()}
                        </a>
                    </td>
                    <td>
                        {if $organization->getOwnerUser()}
                            <a href="{"profiles.update?user_id={$organization->getOwnerUser()->getUserId()}"|fn_url}">
                                {$organization->getOwnerUser()->getName()}
                            </a>
                        {/if}
                    </td>
                    <td class="right nowrap">
                        {capture name="tools_list"}
                            <li>{btn type="list" text=__("edit") href="organizations.update?organization_id={$organization->getOrganizationId()}"}</li>
                            <li>{btn type="list" text=__("delete") class="cm-confirm" href="organizations.delete?organization_id={$organization->getOrganizationId()}&redirect_url={$return_current_url}" method="POST"}</li>
                        {/capture}
                        <div class="hidden-tools">
                            {dropdown content=$smarty.capture.tools_list}
                        </div>
                    </td>
                    <td>
                        {include file="common/select_popup.tpl"
                            id=$organization->getOrganizationId()
                            status=$organization->getStatus()
                            hidden=false
                            notify=false
                            update_controller="organizations"
                            popup_additional_class="dropleft"
                        }
                    </td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    {else}
        <p class="no-items">{__("no_data")}</p>
    {/if}
    {include file="common/pagination.tpl" search=$search}
{/capture}

{capture name="sidebar"}
    {include file="addons/organizations/views/organizations/components/organizations_search_forms.tpl" dispatch="organizations.manage"}
{/capture}

{capture name="adv_buttons"}
    {hook name="organizations:manage_tools"}
        {include file="common/tools.tpl" tool_href="organizations.add" prefix="top" title=__("organizations.new_organization") hide_tools=true icon="icon-plus"}
    {/hook}
{/capture}

{include file="common/mainbox.tpl"
    title=__("organizations")
    content=$smarty.capture.mainbox
    content_id="manage_organizations"
    sidebar=$smarty.capture.sidebar
    adv_buttons=$smarty.capture.adv_buttons
}
