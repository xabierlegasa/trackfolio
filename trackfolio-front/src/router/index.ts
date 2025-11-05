import { createRouter, createWebHistory } from 'vue-router'
import Home from '../views/Home.vue'
import Register from '../views/Register.vue'
import Login from '../views/Login.vue'
import Dashboard from '../views/Dashboard.vue'
import Account from '../views/Account.vue'
import UploadDegiroTransactions from '../views/UploadDegiroTransactions.vue'
import DegiroTransactionsList from '../views/DegiroTransactionsList.vue'
import PortfolioStats from '../views/PortfolioStats.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: Home
    },
    {
      path: '/register',
      name: 'register',
      component: Register
    },
    {
      path: '/login',
      name: 'login',
      component: Login
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: Dashboard
    },
    {
      path: '/account',
      name: 'account',
      component: Account
    },
    {
      path: '/upload-degiro-transactions',
      name: 'upload-degiro-transactions',
      component: UploadDegiroTransactions
    },
    {
      path: '/degiro-transactions',
      name: 'degiro-transactions-list',
      component: DegiroTransactionsList
    },
    {
      path: '/stats',
      name: 'portfolio-stats',
      component: PortfolioStats
    }
  ]
})

export default router

