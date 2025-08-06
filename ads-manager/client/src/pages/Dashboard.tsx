import React from 'react';
import { useQuery } from 'react-query';
import { 
  BarChart, 
  Bar, 
  XAxis, 
  YAxis, 
  CartesianGrid, 
  Tooltip, 
  ResponsiveContainer,
  LineChart,
  Line,
  PieChart,
  Pie,
  Cell
} from 'recharts';
import { 
  TrendingUp, 
  TrendingDown, 
  Users, 
  DollarSign, 
  Eye, 
  MousePointer,
  Target,
  Activity,
  Calendar,
  AlertCircle
} from 'lucide-react';
import { api } from '../contexts/AuthContext';
import { useAuth } from '../contexts/AuthContext';
import StatCard from '../components/dashboard/StatCard';
import RecentActivity from '../components/dashboard/RecentActivity';
import PerformanceChart from '../components/dashboard/PerformanceChart';
import PlatformBreakdown from '../components/dashboard/PlatformBreakdown';
import QuickActions from '../components/dashboard/QuickActions';

const Dashboard: React.FC = () => {
  const { user } = useAuth();

  // Fetch dashboard data
  const { data: dashboardData, isLoading } = useQuery('dashboard', async () => {
    const [overview, campaigns, ads, analytics] = await Promise.all([
      api.get('/analytics/overview'),
      api.get('/campaigns?limit=5&sortBy=createdAt&sortOrder=desc'),
      api.get('/ads?limit=5&sortBy=createdAt&sortOrder=desc'),
      api.get('/analytics/performance?groupBy=day&metrics=impressions,clicks,spend&days=7')
    ]);

    return {
      overview: overview.data,
      recentCampaigns: campaigns.data.campaigns,
      recentAds: ads.data.ads,
      performanceData: analytics.data.performanceData
    };
  });

  // Mock data for demonstration
  const mockData = {
    overview: {
      campaigns: {
        totalCampaigns: 12,
        activeCampaigns: 8,
        totalSpend: 15420.50,
        totalImpressions: 245000,
        totalClicks: 12340,
        totalConversions: 890,
        avgCtr: 5.04,
        avgCpc: 1.25,
        avgCpm: 6.29
      },
      ads: {
        totalAds: 45,
        activeAds: 32,
        totalAdSpend: 12850.75,
        totalAdImpressions: 198000,
        totalAdClicks: 9870,
        totalAdConversions: 720,
        totalEngagement: 1540,
        totalVideoViews: 8900,
        avgAdCtr: 4.98,
        avgAdCpc: 1.30,
        avgAdCpm: 6.49
      },
      platformBreakdown: [
        { platform: 'Facebook', campaigns: 5, spend: 6500, impressions: 120000, clicks: 6000, conversions: 450 },
        { platform: 'Instagram', campaigns: 3, spend: 4200, impressions: 85000, clicks: 3800, conversions: 280 },
        { platform: 'Google', campaigns: 2, spend: 2800, impressions: 40000, clicks: 1540, conversions: 160 },
        { platform: 'LinkedIn', campaigns: 2, spend: 1920.50, impressions: 0, clicks: 0, conversions: 0 }
      ],
      recentPerformance: {
        recentSpend: 2850.25,
        recentImpressions: 45000,
        recentClicks: 2200,
        recentConversions: 180
      }
    }
  };

  const data = dashboardData || mockData;

  // Performance trend data
  const performanceData = [
    { date: 'Mon', impressions: 35000, clicks: 1800, spend: 450 },
    { date: 'Tue', impressions: 42000, clicks: 2100, spend: 520 },
    { date: 'Wed', impressions: 38000, clicks: 1950, spend: 480 },
    { date: 'Thu', impressions: 45000, clicks: 2300, spend: 580 },
    { date: 'Fri', impressions: 41000, clicks: 2050, spend: 510 },
    { date: 'Sat', impressions: 36000, clicks: 1850, spend: 460 },
    { date: 'Sun', impressions: 39000, clicks: 2000, spend: 490 }
  ];

  // Platform breakdown for pie chart
  const platformData = data.overview.platformBreakdown.map(item => ({
    name: item.platform,
    value: item.spend,
    color: getPlatformColor(item.platform)
  }));

  function getPlatformColor(platform: string): string {
    const colors: { [key: string]: string } = {
      'Facebook': '#1877F2',
      'Instagram': '#E4405F',
      'Google': '#4285F4',
      'LinkedIn': '#0A66C2',
      'Twitter': '#1DA1F2',
      'TikTok': '#000000',
      'YouTube': '#FF0000',
      'Pinterest': '#E60023',
      'Snapchat': '#FFFC00'
    };
    return colors[platform] || '#6B7280';
  }

  const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042'];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
            Welcome back, {user?.firstName}!
          </h1>
          <p className="text-gray-600 dark:text-gray-400">
            Here's what's happening with your ads today.
          </p>
        </div>
        <div className="flex items-center space-x-2">
          <span className="text-sm text-gray-500 dark:text-gray-400">
            Last updated: {new Date().toLocaleTimeString()}
          </span>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatCard
          title="Total Spend"
          value={`$${data.overview.campaigns.totalSpend.toLocaleString()}`}
          change="+12.5%"
          changeType="positive"
          icon={DollarSign}
        />
        <StatCard
          title="Impressions"
          value={data.overview.campaigns.totalImpressions.toLocaleString()}
          change="+8.2%"
          changeType="positive"
          icon={Eye}
        />
        <StatCard
          title="Clicks"
          value={data.overview.campaigns.totalClicks.toLocaleString()}
          change="+15.3%"
          changeType="positive"
          icon={MousePointer}
        />
        <StatCard
          title="Conversions"
          value={data.overview.campaigns.totalConversions.toLocaleString()}
          change="+22.1%"
          changeType="positive"
          icon={Target}
        />
      </div>

      {/* Charts Section */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Performance Chart */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Performance Overview
          </h3>
          <ResponsiveContainer width="100%" height={300}>
            <LineChart data={performanceData}>
              <CartesianGrid strokeDasharray="3 3" />
              <XAxis dataKey="date" />
              <YAxis />
              <Tooltip />
              <Line type="monotone" dataKey="impressions" stroke="#8884d8" strokeWidth={2} />
              <Line type="monotone" dataKey="clicks" stroke="#82ca9d" strokeWidth={2} />
              <Line type="monotone" dataKey="spend" stroke="#ffc658" strokeWidth={2} />
            </LineChart>
          </ResponsiveContainer>
        </div>

        {/* Platform Breakdown */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Platform Breakdown
          </h3>
          <ResponsiveContainer width="100%" height={300}>
            <PieChart>
              <Pie
                data={platformData}
                cx="50%"
                cy="50%"
                labelLine={false}
                label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                outerRadius={80}
                fill="#8884d8"
                dataKey="value"
              >
                {platformData.map((entry, index) => (
                  <Cell key={`cell-${index}`} fill={entry.color} />
                ))}
              </Pie>
              <Tooltip />
            </PieChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Recent Activity and Quick Actions */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Recent Activity */}
        <div className="lg:col-span-2 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Recent Activity
          </h3>
          <RecentActivity />
        </div>

        {/* Quick Actions */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            Quick Actions
          </h3>
          <QuickActions />
        </div>
      </div>

      {/* Usage Limits */}
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          Usage & Limits
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          {Object.entries(user?.usage || {}).map(([key, value]) => {
            const limit = user?.limits?.[key as keyof typeof user.limits] || 0;
            const percentage = limit > 0 ? (value / limit) * 100 : 0;
            
            return (
              <div key={key} className="space-y-2">
                <div className="flex justify-between text-sm">
                  <span className="text-gray-600 dark:text-gray-400 capitalize">
                    {key.replace(/([A-Z])/g, ' $1').trim()}
                  </span>
                  <span className="text-gray-900 dark:text-white font-medium">
                    {value} / {limit}
                  </span>
                </div>
                <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                  <div
                    className={`h-2 rounded-full ${
                      percentage > 80 ? 'bg-red-500' : 
                      percentage > 60 ? 'bg-yellow-500' : 'bg-green-500'
                    }`}
                    style={{ width: `${Math.min(percentage, 100)}%` }}
                  />
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
};

export default Dashboard;