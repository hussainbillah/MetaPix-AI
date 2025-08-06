import React from 'react';

const Sidebar: React.FC = () => {
  return (
    <div className="w-64 bg-white dark:bg-gray-800 shadow-lg">
      <div className="p-6">
        <h1 className="text-xl font-bold text-gray-900 dark:text-white">Ads Manager</h1>
      </div>
      {/* Navigation items would go here */}
    </div>
  );
};

export default Sidebar;