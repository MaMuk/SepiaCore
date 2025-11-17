import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import BackendCheck from '../views/BackendCheck.vue'
import Login from '../components/Login.vue'
import Installation from '../views/Installation.vue'
import MainLayout from '../components/MainLayout.vue'
import Dashboard from '../views/Dashboard.vue'
import EntityListView from '../views/EntityListView.vue'
import Settings from '../views/Settings.vue'
import EntityStudio from '../views/EntityStudio.vue'
import UserManagement from '../views/UserManagement.vue'

const routes = [
  {
    path: '/',
    redirect: '/backend-check'
  },
  {
    path: '/backend-check',
    name: 'BackendCheck',
    component: BackendCheck
  },
  {
    path: '/login',
    name: 'Login',
    component: Login,
    meta: { requiresGuest: true }
  },
  {
    path: '/install',
    name: 'Installation',
    component: Installation
  },
  {
    path: '/app',
    component: MainLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/app/dashboard'
      },
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: Dashboard
      },
      {
        path: 'entity/:entity',
        name: 'EntityList',
        component: EntityListView,
        props: true
      },
      {
        path: 'settings',
        name: 'Settings',
        component: Settings
      },
      {
        path: 'entity-studio',
        name: 'EntityStudio',
        component: EntityStudio,
        meta: { requiresAdmin: true }
      },
      {
        path: 'user-management',
        name: 'UserManagement',
        component: UserManagement,
        meta: { requiresAdmin: true }
      }
    ]
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Navigation guard
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next('/login')
  } else if (to.meta.requiresGuest && authStore.isAuthenticated) {
    next('/app')
  } else if (to.meta.requiresAdmin && !authStore.isAdmin) {
    next('/app/settings')
  } else {
    next()
  }
})

export default router

