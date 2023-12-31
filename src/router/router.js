/**
 * @copyright Copyright (c) 2021 Lyseon Techh <contato@lt.coop.br>
 *
 * @author Lyseon Tech <contato@lt.coop.br>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import { selectAction } from '../helpers/SelectAction.js'
import { loadState } from '@nextcloud/initial-state'

Vue.use(Router)

const routes = [
	{
		path: '/reset-password',
		name: 'ResetPassword',
		component: () => import('../views/ResetPassword.vue'),
	},

	// public
	{
		path: '/p/account/files/approve/:uuid',
		name: 'AccountFileApprove',
		component: () => import('../views/SignPDF/SignPDF.vue'),
		props: true,
	},
	{
		path: '/p/sign/:uuid',
		redirect: { name: selectAction(loadState('libresign', 'action', '')) },
		props: true,
	},
	{
		path: '/p/sign/:uuid/pdf',
		name: 'SignPDF',
		component: () => import('../views/SignPDF/SignPDF.vue'),
		props: true,
	},
	{
		path: '/p/sign/:uuid/sign-in',
		name: 'CreateUser',
		component: () => import('../views/CreateUser.vue'),
		props: true,
	},
	{
		path: '/p/sign/:uuid/error',
		name: 'DefaultPageError',
		component: () => import('../views/DefaultPageError.vue'),
		props: true,
	},
	{
		path: '/p/sign/:uuid/success',
		name: 'DefaultPageSuccess',
		component: () => import('../views/DefaultPageSuccess.vue'),
		props: true,
	},
	{
		path: '/p/validation/:uuid',
		name: 'validationFilePublic',
		component: () => import('../views/Validation.vue'),
		props: true,
	},
	{
		path: '/p/sign/:uuid/renew/email',
		name: 'RenewEmail',
		component: () => import('../views/RenewEmail.vue'),
	},

	// internal pages
	{
		path: '/f/',
		redirect: { name: 'requestFiles' },
	},
	{
		path: '/',
		redirect: { name: 'requestFiles' },
	},
	{
		path: '/f/incomplete',
		name: 'incomplete',
		component: () => import('../views/IncompleteCertification.vue'),
	},
	{
		path: '/f/validation',
		name: 'validation',
		component: () => import('../views/Validation.vue'),
	},
	{
		path: '/f/validation/:uuid',
		name: 'validationFile',
		component: () => import('../views/Validation.vue'),
		props: true,
	},
	{
		path: '/f/timeline/sign',
		name: 'signFiles',
		component: () => import('../views/Timeline/Timeline.vue'),
	},
	{
		path: '/f/request',
		name: 'requestFiles',
		component: () => import('../views/Request.vue'),
	},
	{
		path: '/f/account',
		name: 'Account',
		component: () => import('../views/Account/Account.vue'),
	},
	{
		path: '/f/docs/accounts/validation',
		name: 'DocsAccountValidation',
		component: () => import('../views/Documents/AccountValidation.vue'),
	},
	{
		path: '/f/create-password',
		name: 'CreatePassword',
		component: () => import('../views/CreatePassword.vue'),
	},
]

const router = new Router({
	mode: 'history',
	base: generateUrl('/apps/libresign'),
	linkActiveClass: 'active',
	routes,
})

router.beforeEach((to, from, next) => {
	const action = selectAction(loadState('libresign', 'action', ''))
	if (action !== undefined && to.name !== action) {
		next({
			name: action,
		})
	} else if (to.query.redirect === 'CreatePassword') {
		next({ name: 'CreatePassword' })
	} else {
		next()
	}
})

export default router
