import { createRouter, createWebHistory } from 'vue-router'
import Register from '../views/Register.vue'
import Login from '../views/Login.vue'
import Dashboard from '../views/Dashboard.vue'
import Account from '../views/Account.vue'
import UploadDegiroTransactions from '../views/UploadDegiroTransactions.vue'
import DegiroTransactionsList from '../views/DegiroTransactionsList.vue'
import Statistics from '../views/Statistics.vue'
import PortfolioStats from '../views/PortfolioStats.vue'
import Trades from '../views/Trades.vue'
import TradeSummary from '../views/TradeSummary.vue'
import Configuration from '../views/Configuration.vue'
import TaxReturnYears from '../views/TaxReturnYears.vue'
import TaxReturnYearDetail from '../views/TaxReturnYearDetail.vue'
import { useUserStore } from '../stores/userStore'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: { name: 'dashboard' }
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
      component: Dashboard,
      meta: { requiresAuth: true }
    },
    {
      path: '/configuration',
      name: 'configuration',
      component: Configuration,
      meta: { requiresAuth: true }
    },
    {
      path: '/account',
      name: 'account',
      component: Account,
      meta: { requiresAuth: true }
    },
    {
      path: '/upload-degiro-transactions',
      name: 'upload-degiro-transactions',
      component: UploadDegiroTransactions,
      meta: { requiresAuth: true }
    },
    {
      path: '/degiro-transactions',
      name: 'degiro-transactions-list',
      component: DegiroTransactionsList,
      meta: { requiresAuth: true }
    },
    {
      path: '/stats',
      name: 'statistics',
      component: Statistics,
      meta: { requiresAuth: true }
    },
    {
      path: '/portfolio',
      name: 'portfolio',
      component: PortfolioStats,
      meta: { requiresAuth: true }
    },
    {
      path: '/trades',
      name: 'trades',
      component: Trades,
      meta: { requiresAuth: true }
    },
    {
      path: '/trade-summary',
      name: 'trade-summary',
      component: TradeSummary,
      meta: { requiresAuth: true }
    },
    {
      path: '/tax-return',
      name: 'tax-return-years',
      component: TaxReturnYears,
      meta: { requiresAuth: true }
    },
    {
      path: '/tax-return/:year',
      name: 'tax-return-year',
      component: TaxReturnYearDetail,
      props: true,
      meta: { requiresAuth: true }
    }
  ]
})

router.beforeEach(async (to) => {
  const userStore = useUserStore()

  if (to.meta.requiresAuth) {
    if (!userStore.account) {
      try {
        await userStore.fetchAccount()
      } catch {
        return { name: 'login', query: { redirect: to.fullPath } }
      }
    }
  }

  return true
})

export default router
